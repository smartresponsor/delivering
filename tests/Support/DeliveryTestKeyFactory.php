<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Support;

use RuntimeException;

final class DeliveryTestKeyFactory
{
    private static ?string $rsaPrivateKey = null;
    private static ?string $ecPrivateKey = null;

    public static function rsaPrivateKey(): string
    {
        return self::$rsaPrivateKey ??= self::generate([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
    }

    public static function ecPrivateKey(): string
    {
        if (null !== self::$ecPrivateKey) {
            return self::$ecPrivateKey;
        }

        $scalar = random_bytes(32);
        $scalar[0] = chr(ord($scalar[0]) & 0x7f);
        $der = "\x30\x31\x02\x01\x01\x04\x20".$scalar."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
        $body = chunk_split(base64_encode($der), 64, "\n");

        return self::$ecPrivateKey = '-----BEGIN EC'." PRIVATE KEY-----\n".$body.'-----END EC'." PRIVATE KEY-----\n";
    }

    /** @param array<string, int|string> $configuration */
    private static function generate(array $configuration): string
    {
        $locations = openssl_get_cert_locations();
        $iniCaFile = $locations['ini_cafile'] ?? null;
        $opensslConfig = is_string($iniCaFile)
            ? dirname($iniCaFile).DIRECTORY_SEPARATOR.'openssl.cnf'
            : '';
        if (!is_file($opensslConfig)) {
            $opensslConfig = sys_get_temp_dir().DIRECTORY_SEPARATOR.'delivering-test-openssl.cnf';
            file_put_contents(
                $opensslConfig,
                "openssl_conf=openssl_init\n[openssl_init]\nproviders=provider_sect\n[provider_sect]\ndefault=default_sect\n[default_sect]\nactivate=1\n[req]\ndistinguished_name=req_distinguished_name\n[req_distinguished_name]\n",
            );
        }
        $configuration['config'] = $opensslConfig;

        $key = openssl_pkey_new($configuration);
        if (false === $key) {
            throw new RuntimeException('Unable to generate test private key: '.((string) openssl_error_string()));
        }

        $privateKey = '';
        if (!openssl_pkey_export($key, $privateKey, null, ['config' => $opensslConfig]) || '' === $privateKey) {
            throw new RuntimeException('Unable to export test private key.');
        }

        return $privateKey;
    }
}
