<?php

namespace NinjaPortal\Mfa\Services;

use NinjaPortal\Mfa\Contracts\Repositories\MfaChallengeRepositoryInterface;

class PruneMfaChallengesService
{
    public function __construct(protected MfaChallengeRepositoryInterface $challenges) {}

    public function handle(): int
    {
        return $this->challenges->pruneExpired((int) config('portal-mfa.challenge.prune_after_days', 7));
    }
}
