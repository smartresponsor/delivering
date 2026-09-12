<?php

declare(strict_types=1);

namespace App\Delivering\Provider\Push;

use App\Delivering\Exception\DeliveryPermanentTransportException;

final class DeliveryJwtSigner
{
    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $headers
     */
    public static function rs256(array $claims, string $privateKey, array $headers = []): string
    {
        return self::sign($claims, $privateKey, OPENSSL_ALGO_SHA256, false, $headers + ['alg' => 'RS256', 'typ' => 'JWT']);
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $headers
     */
    public static function es256(array $claims, string $privateKey, array $headers = []): string
    {
        return self::sign($claims, $privateKey, OPENSSL_ALGO_SHA256, true, $headers + ['alg' => 'ES256', 'typ' => 'JWT']);
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $headers
     */
    private static function sign(array $claims, string $privateKey, int|string $algorithm, bool $ecdsa, array $headers): string
    {
        $header = self::base64Url((string) json_encode($headers, JSON_THROW_ON_ERROR));
        $payload = self::base64Url((string) json_encode($claims, JSON_THROW_ON_ERROR));
        $input = $header.'.'.$payload;
        $key = openssl_pkey_get_private($privateKey);
        if (false === $key) {
            throw new DeliveryPermanentTransportException('Push provider private key is invalid.');
        }
        $signature = '';
        if (!openssl_sign($input, $signature, $key, $algorithm)) {
            throw new DeliveryPermanentTransportException('Push provider JWT signing failed.');
        }
        if ($ecdsa) {
            $signature = self::ecdsaDerToJose($signature, 32);
        }

        return $input.'.'.self::base64Url($signature);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function ecdsaDerToJose(string $der, int $partLength): string
    {
        $offset = 0;
        if (0x30 !== self::byte($der, $offset++)) {
            throw new DeliveryPermanentTransportException('APNs ES256 signature is not a DER sequence.');
        }
        self::readLength($der, $offset);
        $r = self::readInteger($der, $offset);
        $s = self::readInteger($der, $offset);

        return self::normalizeInteger($r, $partLength).self::normalizeInteger($s, $partLength);
    }

    private static function readInteger(string $der, int &$offset): string
    {
        if (0x02 !== self::byte($der, $offset++)) {
            throw new DeliveryPermanentTransportException('APNs ES256 signature contains an invalid integer.');
        }
        $length = self::readLength($der, $offset);
        $value = substr($der, $offset, $length);
        $offset += $length;

        return $value;
    }

    private static function readLength(string $der, int &$offset): int
    {
        $length = self::byte($der, $offset++);
        if ($length < 0x80) {
            return $length;
        }
        $octets = $length & 0x7f;
        if ($octets < 1 || $octets > 4) {
            throw new DeliveryPermanentTransportException('Push provider DER length is invalid.');
        }
        $length = 0;
        for ($i = 0; $i < $octets; ++$i) {
            $length = ($length << 8) | self::byte($der, $offset++);
        }

        return $length;
    }

    private static function normalizeInteger(string $value, int $length): string
    {
        $value = ltrim($value, "\x00");
        if (strlen($value) > $length) {
            throw new DeliveryPermanentTransportException('Push provider ECDSA signature integer is too large.');
        }

        return str_pad($value, $length, "\x00", STR_PAD_LEFT);
    }

    private static function byte(string $value, int $offset): int
    {
        if ($offset >= strlen($value)) {
            throw new DeliveryPermanentTransportException('Push provider DER signature is truncated.');
        }

        return ord($value[$offset]);
    }
}
