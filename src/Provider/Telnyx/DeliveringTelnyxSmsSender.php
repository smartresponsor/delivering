<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Provider\Telnyx;

use App\Delivering\Exception\DeliveringPermanentTransportException;
use App\Delivering\Exception\DeliveringTransportException;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveringSmsSenderInterface;
use JsonException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class DeliveringTelnyxSmsSender implements DeliveringSmsSenderInterface
{
    private const string ENDPOINT = 'https://api.telnyx.com/v2/messages';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $sender,
    ) {
    }

    public function send(
        string $recipient,
        string $body,
        string $correlationId,
        string $idempotencyKey,
    ): string {
        if ('' === trim($this->apiKey)) {
            throw new DeliveringPermanentTransportException('Telnyx API key is not configured.');
        }

        if (1 !== preg_match('/^\+[1-9]\d{7,14}$/', $this->sender)) {
            throw new DeliveringPermanentTransportException('Telnyx sender must use E.164 format.');
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'auth_bearer' => $this->apiKey,
                'headers' => [
                    'Idempotency-Key' => $idempotencyKey,
                    'X-Correlation-Id' => $correlationId,
                ],
                'json' => [
                    'from' => $this->sender,
                    'to' => $recipient,
                    'text' => $body,
                ],
                'timeout' => 10.0,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            throw new DeliveringTransportException('Telnyx transport request failed.', 0, $exception);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = sprintf(
                'Telnyx rejected the SMS request with HTTP %d: %s',
                $statusCode,
                $content,
            );

            if ($statusCode >= 500 || in_array($statusCode, [408, 429], true)) {
                throw new DeliveringTransportException($message);
            }

            throw new DeliveringPermanentTransportException($message);
        }

        try {
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DeliveringTransportException('Telnyx returned invalid JSON.', 0, $exception);
        }

        $providerMessageId = $payload['data']['id'] ?? null;

        if (!is_string($providerMessageId) || '' === $providerMessageId) {
            throw new DeliveringTransportException('Telnyx response does not contain a message ID.');
        }

        return $providerMessageId;
    }
}
