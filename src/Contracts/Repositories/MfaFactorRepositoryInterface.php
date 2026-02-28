<?php

namespace NinjaPortal\Mfa\Contracts\Repositories;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use NinjaPortal\Mfa\Models\MfaFactor;

interface MfaFactorRepositoryInterface
{
    /**
     * @return Collection<int, MfaFactor>
     */
    public function listForActor(Authenticatable $actor): Collection;

    public function findByActorAndDriver(Authenticatable $actor, string $driver): ?MfaFactor;

    public function firstOrNewByActorAndDriver(Authenticatable $actor, string $driver): MfaFactor;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(MfaFactor $factor, array $attributes = []): MfaFactor;

    public function delete(int|string|MfaFactor $factor): bool;

    public function clearPrimaryForActor(Authenticatable $actor): void;
}
