<?php

namespace NinjaPortal\Mfa\Repositories;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use NinjaPortal\Mfa\Contracts\Repositories\MfaFactorRepositoryInterface;
use NinjaPortal\Mfa\Models\MfaFactor;
use NinjaPortal\Portal\Common\Repositories\BaseRepository;

/**
 * @extends BaseRepository<MfaFactor>
 */
class MfaFactorRepository extends BaseRepository implements MfaFactorRepositoryInterface
{
    public function listForActor(Authenticatable $actor): Collection
    {
        return MfaFactor::query()
            ->where('authenticatable_type', $actor::class)
            ->where('authenticatable_id', (int) $actor->getAuthIdentifier())
            ->orderByDesc('is_primary')
            ->orderBy('driver')
            ->get();
    }

    public function findByActorAndDriver(Authenticatable $actor, string $driver): ?MfaFactor
    {
        $factor = MfaFactor::query()->where([
            'authenticatable_type' => $actor::class,
            'authenticatable_id' => (int) $actor->getAuthIdentifier(),
            'driver' => $driver,
        ])->first();

        return $factor instanceof MfaFactor ? $factor : null;
    }

    public function firstOrNewByActorAndDriver(Authenticatable $actor, string $driver): MfaFactor
    {
        return MfaFactor::query()->firstOrNew([
            'authenticatable_type' => $actor::class,
            'authenticatable_id' => (int) $actor->getAuthIdentifier(),
            'driver' => $driver,
        ]);
    }

    public function save(MfaFactor $factor, array $attributes = []): MfaFactor
    {
        if ($attributes !== []) {
            $factor->fill($attributes);
        }

        $factor->save();

        return $factor->refresh();
    }

    public function delete(int|string|MfaFactor $factor): bool
    {
        $model = $factor instanceof MfaFactor ? $factor : $this->resolve($factor);

        return (bool) $model->delete();
    }

    public function clearPrimaryForActor(Authenticatable $actor): void
    {
        MfaFactor::query()->where([
            'authenticatable_type' => $actor::class,
            'authenticatable_id' => (int) $actor->getAuthIdentifier(),
        ])->update(['is_primary' => false]);
    }
}
