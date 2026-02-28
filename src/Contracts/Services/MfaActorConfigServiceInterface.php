<?php

namespace NinjaPortal\Mfa\Contracts\Services;

interface MfaActorConfigServiceInterface
{
    public function actorEnabled(string $context): bool;

    public function actorRequired(string $context): bool;

    public function actorAllowUserDisable(string $context): bool;

    /**
     * @return array<int, string>
     */
    public function actorAllowedDrivers(string $context): array;

    public function actorDefaultDriver(string $context): ?string;

    /**
     * @return array<string, mixed>
     */
    public function actorConfig(string $context): array;
}
