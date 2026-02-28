<?php

namespace NinjaPortal\Mfa\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

class TotpService
{
    public function __construct(protected Base32 $base32) {}

    public function generateSecret(int $byteLength = 20): string
    {
        if ($byteLength < 10) {
            throw new InvalidArgumentException('TOTP secret length must be at least 10 bytes.');
        }

        return $this->base32->encode(random_bytes($byteLength));
    }

    public function verifyCode(string $secret, string $code, int $window = 1, int $period = 30, int $digits = 6, ?int $timestamp = null): bool
    {
        $timestamp ??= time();
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d+$/', $code)) {
            return false;
        }

        $counter = intdiv($timestamp, max(1, $period));
        $window = max(0, $window);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $expected = $this->at($secret, $counter + $offset, $digits);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    public function at(string $secret, int $counter, int $digits = 6): string
    {
        $key = $this->base32->decode($secret);
        $counterBytes = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );
        $mod = 10 ** max(1, $digits);

        return str_pad((string) ($value % $mod), $digits, '0', STR_PAD_LEFT);
    }

    public function buildOtpAuthUri(string $secret, string $issuer, string $accountLabel, int $digits = 6, int $period = 30): string
    {
        $label = rawurlencode($issuer.':'.$accountLabel);
        $issuerParam = rawurlencode($issuer);
        $secretParam = rawurlencode($secret);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&digits=%d&period=%d',
            $label,
            $secretParam,
            $issuerParam,
            $digits,
            $period
        );
    }
}
