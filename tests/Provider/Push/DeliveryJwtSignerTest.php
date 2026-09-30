<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Push;

use App\Delivering\Provider\Push\DeliveryJwtSigner;
use App\Delivering\Tests\Support\DeliveryTestKeyFactory;
use PHPUnit\Framework\TestCase;

final class DeliveryJwtSignerTest extends TestCase
{
    public function testRs256ProducesThreeJwtSegments(): void
    {
        $jwt = DeliveryJwtSigner::rs256(['iss' => 'service@example.test', 'iat' => 1], DeliveryTestKeyFactory::rsaPrivateKey());

        self::assertCount(3, explode('.', $jwt));
    }

    public function testEs256ProducesJoseSignatureWithExpectedLength(): void
    {
        $jwt = DeliveryJwtSigner::es256(['iss' => 'TEAM', 'iat' => 1], DeliveryTestKeyFactory::ecPrivateKey(), ['kid' => 'KEY']);
        $segments = explode('.', $jwt);
        self::assertCount(3, $segments);
        $signature = base64_decode(strtr($segments[2], '-_', '+/').str_repeat('=', (4 - strlen($segments[2]) % 4) % 4), true);
        self::assertIsString($signature);
        self::assertSame(64, strlen($signature));
    }

    public function testRs256RejectsInvalidPrivateKey(): void
    {
        $this->expectException(\App\Delivering\Exception\DeliveryPermanentTransportException::class);
        $this->expectExceptionMessage('Push provider private key is invalid.');

        DeliveryJwtSigner::rs256(['iss' => 'issuer'], 'not-a-private-key');
    }

    public function testEs256RejectsInvalidPrivateKey(): void
    {
        $this->expectException(\App\Delivering\Exception\DeliveryPermanentTransportException::class);
        $this->expectExceptionMessage('Push provider private key is invalid.');

        DeliveryJwtSigner::es256(['iss' => 'issuer'], 'not-a-private-key');
    }


}
