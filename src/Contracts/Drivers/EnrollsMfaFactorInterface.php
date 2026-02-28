<?php

namespace NinjaPortal\Mfa\Contracts\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use NinjaPortal\Mfa\Models\MfaFactor;

interface EnrollsMfaFactorInterface
{
    /**
     * Begin enrollment for a factor and return payload the client needs (ex: otpauth URI).
     *
     * @param  array<string, mixed>  $input
     * @return array{factor:MfaFactor,payload:array<string,mixed>}
     */
    public function beginEnrollment(Authenticatable $actor, string $context, MfaFactor $factor, array $input = []): array;

    /**
     * @param  array<string, mixed>  $input
     */
    public function confirmEnrollment(Authenticatable $actor, string $context, MfaFactor $factor, array $input = []): MfaFactor;
}
