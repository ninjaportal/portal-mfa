<?php

namespace NinjaPortal\Mfa\Contracts\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use NinjaPortal\Mfa\Models\MfaChallenge;
use NinjaPortal\Mfa\Models\MfaFactor;

interface MfaChallengeServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function createLoginChallenge(Authenticatable $actor, string $context): array;

    /**
     * @return array<string, mixed>
     */
    public function verifyLoginChallenge(string $context, string $challengeToken, string $code): array;

    /**
     * @return array<string, mixed>
     */
    public function resendLoginChallenge(string $context, string $challengeToken): array;

    /**
     * Create a non-login challenge for a specific factor (eg. email-otp enrollment verification).
     *
     * @return array<string, mixed>
     */
    public function createFactorChallenge(Authenticatable $actor, string $context, MfaFactor $factor, string $purpose): array;

    /**
     * Verify a non-login factor challenge and return the completed challenge.
     */
    public function verifyFactorChallenge(string $context, string $challengeToken, string $code, string $purpose): MfaChallenge;
}
