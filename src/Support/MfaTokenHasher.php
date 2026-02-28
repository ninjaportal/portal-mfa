<?php

namespace NinjaPortal\Mfa\Support;

class MfaTokenHasher
{
    public function makeToken(int $length = 64): string
    {
        $length = max(32, $length);
        $bytes = (int) ceil($length / 2);

        return substr(bin2hex(random_bytes($bytes)), 0, $length);
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
