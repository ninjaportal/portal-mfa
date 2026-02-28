<?php

namespace NinjaPortal\Mfa\Contracts\Services;

use Illuminate\Contracts\Auth\Authenticatable;

interface MfaProfileServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getSettingsPayload(Authenticatable $actor, string $context): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function updateSettings(Authenticatable $actor, string $context, array $attributes): array;

    public function requiresMfa(Authenticatable $actor, string $context): bool;

    public function shouldChallengeOnLogin(Authenticatable $actor, string $context): bool;
}
