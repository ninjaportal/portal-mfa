<?php

namespace NinjaPortal\Mfa\Contracts\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use NinjaPortal\Mfa\Models\MfaChallenge;
use NinjaPortal\Mfa\Models\MfaFactor;

interface MfaDriverInterface
{
    public function key(): string;

    public function supportsResend(): bool;

    /**
     * Prepare a challenge and return driver-specific payload for the client.
     *
     * @return array<string, mixed>
     */
    public function prepareChallenge(MfaChallenge $challenge, MfaFactor $factor, Authenticatable $actor, string $context): array;

    public function verifyChallenge(MfaChallenge $challenge, MfaFactor $factor, string $code, Authenticatable $actor, string $context): bool;

    /**
     * @return array<string, mixed>
     */
    public function resendChallenge(MfaChallenge $challenge, MfaFactor $factor, Authenticatable $actor, string $context): array;
}
