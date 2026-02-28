<?php

namespace NinjaPortal\Mfa\Support;

class MfaMask
{
    public function email(string $email): string
    {
        $email = trim($email);
        if ($email === '' || ! str_contains($email, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $email, 2);
        $localMasked = strlen($local) <= 2
            ? substr($local, 0, 1).'*'
            : substr($local, 0, 1).str_repeat('*', max(1, strlen($local) - 2)).substr($local, -1);

        return $localMasked.'@'.$domain;
    }
}
