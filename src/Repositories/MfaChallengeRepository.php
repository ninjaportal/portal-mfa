<?php

namespace NinjaPortal\Mfa\Repositories;

use Illuminate\Contracts\Auth\Authenticatable;
use NinjaPortal\Mfa\Contracts\Repositories\MfaChallengeRepositoryInterface;
use NinjaPortal\Mfa\Models\MfaChallenge;
use NinjaPortal\Mfa\Models\MfaFactor;
use NinjaPortal\Portal\Common\Repositories\BaseRepository;

/**
 * @extends BaseRepository<MfaChallenge>
 */
class MfaChallengeRepository extends BaseRepository implements MfaChallengeRepositoryInterface
{
    public function createForActor(Authenticatable $actor, MfaFactor $factor, array $attributes): MfaChallenge
    {
        /** @var MfaChallenge $challenge */
        $challenge = MfaChallenge::query()->create(array_merge($attributes, [
            'authenticatable_type' => $actor::class,
            'authenticatable_id' => (int) $actor->getAuthIdentifier(),
            'mfa_factor_id' => $factor->getKey(),
            'driver' => $factor->driver,
        ]));

        return $challenge;
    }

    public function findPendingByTokenHash(string $tokenHash, ?string $context = null, ?string $purpose = null): ?MfaChallenge
    {
        $query = MfaChallenge::query()->where('token_hash', $tokenHash)
            ->whereNull('completed_at')
            ->whereNull('invalidated_at')
            ->where('expires_at', '>', now());

        if ($context !== null) {
            $query->where('context', $context);
        }

        if ($purpose !== null) {
            $query->where('purpose', $purpose);
        }

        $challenge = $query->first();

        return $challenge instanceof MfaChallenge ? $challenge : null;
    }

    public function save(MfaChallenge $challenge): MfaChallenge
    {
        $challenge->save();

        return $challenge->refresh();
    }

    public function invalidateOpenChallengesForActorDriver(Authenticatable $actor, string $driver, string $purpose): void
    {
        MfaChallenge::query()
            ->where('authenticatable_type', $actor::class)
            ->where('authenticatable_id', (int) $actor->getAuthIdentifier())
            ->where('driver', $driver)
            ->where('purpose', $purpose)
            ->whereNull('completed_at')
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => now()]);
    }

    public function pruneExpired(int $olderThanDays): int
    {
        return MfaChallenge::query()
            ->where('expires_at', '<', now()->subDays(max(1, $olderThanDays)))
            ->delete();
    }
}
