<?php

namespace NinjaPortal\Mfa\Services;

use NinjaPortal\Mfa\Contracts\Services\MfaActorConfigServiceInterface;

class MfaActorConfigService implements MfaActorConfigServiceInterface
{
    public function actorEnabled(string $context): bool
    {
        return (bool) data_get($this->actorConfig($context), 'enabled', true);
    }

    public function actorRequired(string $context): bool
    {
        return (bool) data_get($this->actorConfig($context), 'required', false);
    }

    public function actorAllowUserDisable(string $context): bool
    {
        return (bool) data_get($this->actorConfig($context), 'allow_user_disable', true);
    }

    public function actorAllowedDrivers(string $context): array
    {
        return array_values(array_filter((array) data_get($this->actorConfig($context), 'allowed_drivers', []), fn ($v) => is_string($v) && $v !== ''));
    }

    public function actorDefaultDriver(string $context): ?string
    {
        $driver = data_get($this->actorConfig($context), 'default_driver');

        return is_string($driver) && $driver !== '' ? $driver : null;
    }

    public function actorConfig(string $context): array
    {
        $key = strtolower(trim($context)) === 'admin' ? 'admin' : 'consumer';

        return (array) config("portal-mfa.actors.{$key}", []);
    }
}
