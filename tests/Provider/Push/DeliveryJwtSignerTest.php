<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Push;

use App\Delivering\Provider\Push\DeliveryJwtSigner;
use PHPUnit\Framework\TestCase;

final class DeliveryJwtSignerTest extends TestCase
{
    public function testRs256ProducesThreeJwtSegments(): void
    {
        $jwt = DeliveryJwtSigner::rs256(['iss' => 'service@example.test', 'iat' => 1], self::RSA_PRIVATE_KEY);

        self::assertCount(3, explode('.', $jwt));
    }

    public function testEs256ProducesJoseSignatureWithExpectedLength(): void
    {
        $jwt = DeliveryJwtSigner::es256(['iss' => 'TEAM', 'iat' => 1], self::EC_PRIVATE_KEY, ['kid' => 'KEY']);
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

    private const string RSA_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCmpinQ9ZC7Fz1O
RLzJXl2h5YZkouohI6AbRb0ocHHTPW2MyQzPCEktWsSg7D7Ni4vh9tfCNxmYBY/Z
R3hv8gbrZ7c9kAHa07w95SLvrkHY3wVgagjDnNoSdis+X6gLbACB/u227Bjak1ts
B02CbF4aHWZ5pNh9R3TBACrmfPhIn20qRkTLfIUJSgK7/8XpQZQ5mHg7ztcWPzzu
URdLojuT9DokQ5wnIQAgZQD8erxo5oCPg4Ete4GK2ev6ILT9kIE10gx7zBIhzotp
r0RI+PkxQZIJxedQWpiY8EeJjtq6u/yIpSkssttsJcv1ErdxJCmfETHN+PbRrto8
PSUtwN+VAgMBAAECggEADI/dpWrquDJWGeEe6qj/3YqBOR3PZfnFrGNIkSn6EpEr
VwpNZQp8BadgHLyiNqP3F+yfsqas+bjVm+yCHMzS47TY6xLraN+UGDBTvrB/4IVd
5jjSpKLdYg3hUEgtUSsY1gkYSd/TNyAWK0xY9eS4VTdJIxCfjGtcrDMYiMZRKvF7
xNcrOOhFiSWSgCvfbTLM8d/zBdO9JmTsEQt7HIYchEnleAsByVa9ri5A5bur9ioY
y02KFcjMPmpFx6cxKA/yVVBst7YcocWYWStLrxms1ERVHwQwxXpNF1BDU46dMUPT
x7tJBiZr3JgUyGGJ6DscltAdhTJ0LLTVgvTPYXMOwwKBgQDVg/257Z9WSxKreyaF
/7UathaVGukuiJk0cvfZeGiVOZve7uZhr1KEicagQsAwe1BnI4OcI4P1tBd0LjRJ
DobbxzgqsHH+totPnI31ajGFFZ2WuKKafW5AYwawBSfaBA7SvYMwtFtRFwDsH5U5
HKcc2cJgeNgCsQ62WJWfxKonqwKBgQDHzuVzU+bicTBJINs0ghhqhRxTOhx+cUya
Lza4oUAbormeMGhwYtx27bkatWXsQvLDswcWEeSUILKMkCIZj6YWgd1ksYuvOOG4
HNkPqemh7+44RhwmujfyUVMBBgz8qR2lJfGtovVMJcz5/xXJV8ucIFhg5rf7OiWB
VydEI2TVvwKBgQCpZLA1hBn3glPrjCaCBN6PtIqx/Mmmy2SQwe10sRx3116cPXi1
YzzaPdxBZPPJAuxFB13w0BRvKFO7LrT4iPfhAWrEI3wtEnHv1Uqiu39SEFYYL5+B
ZaXEm0vA9jYptzJzazrbtxsDeHaY3m2rA9po/zJBC16EtCfx7tG2EXbVRQKBgQCu
I/X6Y5+Qr5Gjyo0B4HijLcwYBUecM+bNYmTQ2UjkTRh1dD8x5Be9V0bCrmJcXaTz
Ru7gH0wWhcDXnS77FCVu7FQmVE8nse2X5xyO+El1J4V5ajFS1223NYWgGMPs2P/L
VZyi9qnPagqRv+4fAvOj6NTd73dd77mMVocUbbyORQKBgD5WH7TPPLTgd4FgumB5
ODLXBqUjjteKURD2+EC6UBI58q3dMiSj35yH93L0IPbQxn4QY8gPe24XXXVTbZUu
PiJxRs27GTo4/UrwPMFalytdh42q9UXRXYVMa1BV1POEesUTIlZyRznY67TERoSx
A+Q6e7vn/daVY5ezU9NV67tB
-----END PRIVATE KEY-----
PEM;

    private const string EC_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQg3AonhNxooQPe7SSb
R5pXvJZphkqcYTbAILO2YNcmv1qhRANCAAQpexmx1AimwyAw3iOGECbkNU3SH7mU
fysI+oJiBwLPnNBk1aOqAoLezeO2H1ipBE4r5VDwG/NwXjqsnUV+Te+A
-----END PRIVATE KEY-----
PEM;
}
