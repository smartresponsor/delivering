<?php

declare(strict_types=1);

namespace App\Delivering\Provider\Push;

use App\Delivering\Exception\DeliveringPermanentTransportException;
use App\Delivering\Exception\DeliveringTransportException;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveringPushProviderInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class DeliveringApnsPushProvider implements DeliveringPushProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $teamId,
        private string $keyId,
        private string $privateKey,
        private string $topicMapJson,
        private string $environment = 'production',
    ) {
    }

    public function supports(string $platform): bool
    {
        return 'ios' === $platform;
    }

    public function send(string $token, string $appKey, string $title, string $body, ?string $actionUrl, array $payload, string $correlationId, string $idempotencyKey): string
    {
        foreach ([$this->teamId, $this->keyId, $this->privateKey] as $value) {
            if ('' === trim($value)) {
                throw new DeliveringPermanentTransportException('APNs credentials are not configured.');
            }
        }
        $topics = $this->decodeMap($this->topicMapJson, 'APNs topic map');
        $topic = $topics[$appKey] ?? null;
        if (!is_string($topic) || '' === trim($topic)) {
            throw new DeliveringPermanentTransportException(sprintf('APNs topic is not configured for appKey "%s".', $appKey));
        }
        $environment = strtolower(trim($this->environment));
        if (!in_array($environment, ['development', 'production'], true)) {
            throw new DeliveringPermanentTransportException('APNs environment must be development or production.');
        }
        $jwt = DeliveringJwtSigner::es256(
            ['iss' => $this->teamId, 'iat' => time()],
            str_replace('\\n', "\n", $this->privateKey),
            ['kid' => $this->keyId],
        );
        $endpoint = 'development' === $environment
            ? 'https://api.sandbox.push.apple.com'
            : 'https://api.push.apple.com';
        $custom = $payload;
        if (null !== $actionUrl && '' !== trim($actionUrl)) {
            $custom['actionUrl'] = $actionUrl;
        }
        try {
            $response = $this->httpClient->request('POST', $endpoint.'/3/device/'.rawurlencode($token), [
                'http_version' => '2.0',
                'headers' => [
                    'authorization' => 'bearer '.$jwt,
                    'apns-topic' => $topic,
                    'apns-push-type' => 'alert',
                    'apns-priority' => '10',
                ],
                'json' => ['aps' => ['alert' => ['title' => $title, 'body' => $body], 'sound' => 'default']] + $custom,
                'timeout' => 10.0,
            ]);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $headers = $response->getHeaders(false);
        } catch (TransportExceptionInterface $exception) {
            throw new DeliveringTransportException('APNs transport request failed.', 0, $exception);
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            $decoded = json_decode($content, true);
            $reason = is_array($decoded) && is_string($decoded['reason'] ?? null) ? $decoded['reason'] : null;
            $message = DeliveringPushFailureClassifier::label('APNs', $statusCode, $reason);
            if (DeliveringPushFailureClassifier::apnsIsTransient($statusCode, $reason)) {
                throw new DeliveringTransportException($message);
            }
            throw new DeliveringPermanentTransportException($message);
        }

        return (string) (($headers['apns-id'][0] ?? null) ?: $correlationId.':'.$idempotencyKey);
    }

    /** @return array<string, mixed> */
    private function decodeMap(string $json, string $label): array
    {
        $value = json_decode($json, true);
        if (!is_array($value)) {
            throw new DeliveringPermanentTransportException($label.' must be valid JSON object.');
        }

        return $value;
    }
}
