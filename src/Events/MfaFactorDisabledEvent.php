<?php

namespace NinjaPortal\Mfa\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use NinjaPortal\Mfa\Models\MfaFactor;

class MfaFactorDisabledEvent
{
    public function __construct(
        public readonly string $context,
        public readonly Authenticatable $actor,
        public readonly MfaFactor $factor
    ) {}
}
