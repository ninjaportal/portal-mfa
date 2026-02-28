<?php

namespace NinjaPortal\Mfa\Contracts\Repositories;

use Illuminate\Contracts\Auth\Authenticatable;
use NinjaPortal\Mfa\Models\MfaChallenge;
use NinjaPortal\Mfa\Models\MfaFactor;

interface MfaChallengeRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForActor(Authenticatable $actor, MfaFactor $factor, array $attributes): MfaChallenge;

    public function findPendingByTokenHash(string $tokenHash, ?string $context = null, ?string $purpose = null): ?MfaChallenge;

    public function save(MfaChallenge $challenge): MfaChallenge;

    public function invalidateOpenChallengesForActorDriver(Authenticatable $actor, string $driver, string $purpose): void;

    public function pruneExpired(int $olderThanDays): int;
}
