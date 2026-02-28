<?php

namespace NinjaPortal\Mfa\Services;

use InvalidArgumentException;
use NinjaPortal\Mfa\Contracts\Drivers\MfaDriverInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaDriverManagerInterface;

class MfaDriverManager implements MfaDriverManagerInterface
{
    /** @var array<string, MfaDriverInterface> */
    protected array $resolved = [];

    public function driver(string $key): MfaDriverInterface
    {
        $key = trim($key);
        if ($key === '') {
            throw new InvalidArgumentException('MFA driver key is required.');
        }

        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $map = (array) config('portal-mfa.drivers.map', []);
        $class = $map[$key] ?? null;
        if (! is_string($class) || $class === '') {
            throw new InvalidArgumentException(sprintf('MFA driver [%s] is not configured.', $key));
        }

        $driver = app($class);
        if (! $driver instanceof MfaDriverInterface) {
            throw new InvalidArgumentException(sprintf('Configured MFA driver [%s] must implement %s.', $class, MfaDriverInterface::class));
        }

        return $this->resolved[$key] = $driver;
    }

    public function configuredDriverKeys(): array
    {
        return array_values(array_filter(array_keys((array) config('portal-mfa.drivers.map', [])), 'is_string'));
    }
}
