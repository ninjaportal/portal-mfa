<?php

namespace NinjaPortal\Mfa\Contracts\Repositories;

use Illuminate\Contracts\Auth\Authenticatable;
use NinjaPortal\Mfa\Models\MfaProfile;

interface MfaProfileRepositoryInterface
{
    public function firstOrCreateForActor(Authenticatable $actor): MfaProfile;

    public function findForActor(Authenticatable $actor): ?MfaProfile;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(MfaProfile $profile, array $attributes): MfaProfile;
}
