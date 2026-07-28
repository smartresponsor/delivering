<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Provider\Telnyx;

use DateTimeImmutable;

final readonly class DeliveringTelnyxWebhookSignatureVerifier
{
    public function __construct(
        private string $publicKey,
        private int $toleranceSeconds = 300,
    ) {
    }

    public function verify(string $payload, string $signature, string $timestamp, ?DateTimeImmutable $now = null): bool
    {
        if ('' === $this->publicKey || '' === $signature || !ctype_digit($timestamp)) {
            return false;
        }

        $now ??= new DateTimeImmutable();
        if (abs($now->getTimestamp() - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $decodedSignature = base64_decode($signature, true);
        $decodedPublicKey = base64_decode($this->publicKey, true);
        if (false === $decodedSignature || false === $decodedPublicKey) {
            return false;
        }

        if (SODIUM_CRYPTO_SIGN_BYTES !== strlen($decodedSignature)) {
            return false;
        }

        if (SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen($decodedPublicKey)) {
            return false;
        }

        return sodium_crypto_sign_verify_detached(
            $decodedSignature,
            $timestamp.'|'.$payload,
            $decodedPublicKey,
        );
    }
}
