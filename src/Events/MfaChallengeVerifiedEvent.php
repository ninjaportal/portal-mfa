<?php

namespace NinjaPortal\Mfa\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use NinjaPortal\Mfa\Models\MfaChallenge;

class MfaChallengeVerifiedEvent
{
    public function __construct(
        public readonly string $context,
        public readonly string $purpose,
        public readonly Authenticatable $actor,
        public readonly MfaChallenge $challenge
    ) {}
}
