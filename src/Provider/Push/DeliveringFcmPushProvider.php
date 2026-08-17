<?php

declare(strict_types=1);

namespace App\Delivering\Provider\Push;

use App\Delivering\Exception\DeliveringPermanentTransportException;
use App\Delivering\Exception\DeliveringTransportException;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveringPushProviderInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class DeliveringFcmPushProvider implements DeliveringPushProviderInterface
{
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $serviceAccountJson,
        private readonly string $projectMapJson,
    ) {
    }

    public function supports(string $platform): bool
    {
        return 'android' === $platform;
    }

    public function send(string $token, string $appKey, string $title, string $body, ?string $actionUrl, array $payload, string $correlationId, string $idempotencyKey): string
    {
        $projects = $this->decodeMap($this->projectMapJson, 'FCM project map');
        $projectId = $projects[$appKey] ?? null;
        if (!is_string($projectId) || '' === trim($projectId)) {
            throw new DeliveringPermanentTransportException(sprintf('FCM project is not configured for appKey "%s".', $appKey));
        }
        $data = [];
        foreach ($payload as $key => $value) {
            $data[(string) $key] = is_scalar($value) || null === $value
                ? (string) $value
                : (string) json_encode($value, JSON_THROW_ON_ERROR);
        }
        if (null !== $actionUrl && '' !== trim($actionUrl)) {
            $data['actionUrl'] = $actionUrl;
        }
        $data['correlationId'] = $correlationId;
        $data['idempotencyKey'] = $idempotencyKey;

        try {
            $response = $this->httpClient->request('POST', sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', rawurlencode($projectId)), [
                'auth_bearer' => $this->accessToken(),
                'json' => [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => $data,
                    ],
                ],
                'timeout' => 10.0,
            ]);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            throw new DeliveringTransportException('FCM transport request failed.', 0, $exception);
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = sprintf('FCM rejected the push request with HTTP %d: %s', $statusCode, $content);
            if ($statusCode >= 500 || in_array($statusCode, [408, 429], true)) {
                throw new DeliveringTransportException($message);
            }
            throw new DeliveringPermanentTransportException($message);
        }
        $decoded = json_decode($content, true);
        $name = is_array($decoded) ? ($decoded['name'] ?? null) : null;
        if (!is_string($name) || '' === $name) {
            throw new DeliveringTransportException('FCM response does not contain a message name.');
        }

        return $name;
    }

    private function accessToken(): string
    {
        if (null !== $this->accessToken && time() < $this->accessTokenExpiresAt - 60) {
            return $this->accessToken;
        }
        $account = $this->decodeMap(str_replace('\\n', "\n", $this->serviceAccountJson), 'FCM service account JSON');
        $email = $account['client_email'] ?? null;
        $privateKey = $account['private_key'] ?? null;
        $tokenUri = $account['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        if (!is_string($email) || '' === $email || !is_string($privateKey) || '' === $privateKey || !is_string($tokenUri) || '' === $tokenUri) {
            throw new DeliveringPermanentTransportException('FCM service account JSON is incomplete.');
        }
        $now = time();
        $assertion = DeliveringJwtSigner::rs256([
            'iss' => $email,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $privateKey);

        try {
            $response = $this->httpClient->request('POST', $tokenUri, [
                'body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ],
                'timeout' => 10.0,
            ]);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            throw new DeliveringTransportException('FCM OAuth token request failed.', 0, $exception);
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new DeliveringPermanentTransportException(sprintf('FCM OAuth token request failed with HTTP %d: %s', $statusCode, $content));
        }
        $decoded = json_decode($content, true);
        $accessToken = is_array($decoded) ? ($decoded['access_token'] ?? null) : null;
        $expiresIn = is_array($decoded) ? (int) ($decoded['expires_in'] ?? 3600) : 3600;
        if (!is_string($accessToken) || '' === $accessToken) {
            throw new DeliveringTransportException('FCM OAuth response does not contain access_token.');
        }
        $this->accessToken = $accessToken;
        $this->accessTokenExpiresAt = time() + max(60, $expiresIn);

        return $accessToken;
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
