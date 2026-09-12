<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Telnyx;

use App\Delivering\Provider\Telnyx\DeliveryTelnyxWebhookSignatureVerifier;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DeliveryTelnyxWebhookSignatureVerifierTest extends TestCase
{
    public function testVerifyValidSignature(): void
    {
        $keyPair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keyPair);
        $publicKey = sodium_crypto_sign_publickey($keyPair);
        $timestamp = '1785067200';
        $payload = '{"data":{"id":"event-123"}}';
        $signature = sodium_crypto_sign_detached($timestamp.'|'.$payload, $secretKey);
        $verifier = new DeliveryTelnyxWebhookSignatureVerifier(base64_encode($publicKey));

        self::assertTrue($verifier->verify(
            $payload,
            base64_encode($signature),
            $timestamp,
            new DateTimeImmutable('@1785067200'),
        ));
    }

    public function testRejectStaleTimestamp(): void
    {
        $keyPair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keyPair);
        $verifier = new DeliveryTelnyxWebhookSignatureVerifier(base64_encode($publicKey));

        self::assertFalse($verifier->verify(
            'payload',
            base64_encode(str_repeat('x', 64)),
            '100',
            new DateTimeImmutable('@1000'),
        ));
    }
}
