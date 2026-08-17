<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Push;

use App\Delivering\Provider\Push\DeliveringJwtSigner;
use PHPUnit\Framework\TestCase;

final class DeliveringJwtSignerTest extends TestCase
{
    public function testRs256ProducesThreeJwtSegments(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        if (false === $key) {
            self::markTestSkipped('Local OpenSSL cannot generate an ephemeral RSA key.');
        }
        self::assertTrue(openssl_pkey_export($key, $privateKey));

        $jwt = DeliveringJwtSigner::rs256(['iss' => 'service@example.test', 'iat' => 1], $privateKey);
        self::assertCount(3, explode('.', $jwt));
    }

    public function testEs256ProducesJoseSignatureWithExpectedLength(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if (false === $key) {
            self::markTestSkipped('Local OpenSSL cannot generate an ephemeral EC key.');
        }
        self::assertTrue(openssl_pkey_export($key, $privateKey));

        $jwt = DeliveringJwtSigner::es256(['iss' => 'TEAM', 'iat' => 1], $privateKey, ['kid' => 'KEY']);
        $segments = explode('.', $jwt);
        self::assertCount(3, $segments);
        $signature = base64_decode(strtr($segments[2], '-_', '+/').str_repeat('=', (4 - strlen($segments[2]) % 4) % 4), true);
        self::assertIsString($signature);
        self::assertSame(64, strlen($signature));
    }
}
