<?php

namespace NinjaPortal\Mfa\Contracts\Services;

use Illuminate\Contracts\Auth\Authenticatable;

interface MfaFactorServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function beginAuthenticatorEnrollment(Authenticatable $actor, string $context, ?string $label = null): array;

    /**
     * @return array<string, mixed>
     */
    public function confirmAuthenticatorEnrollment(Authenticatable $actor, string $context, string $code): array;

    /**
     * @return array<string, mixed>
     */
    public function beginEmailOtpEnrollment(Authenticatable $actor, string $context): array;

    /**
     * @return array<string, mixed>
     */
    public function confirmEmailOtpEnrollment(Authenticatable $actor, string $context, string $challengeToken, string $code): array;

    public function disableFactor(Authenticatable $actor, string $context, string $driver): void;
}
