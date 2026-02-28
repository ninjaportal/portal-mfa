<?php

namespace NinjaPortal\Mfa\Contracts\Services;

use NinjaPortal\Mfa\Contracts\Drivers\MfaDriverInterface;

interface MfaDriverManagerInterface
{
    public function driver(string $key): MfaDriverInterface;

    /**
     * @return array<int, string>
     */
    public function configuredDriverKeys(): array;
}
