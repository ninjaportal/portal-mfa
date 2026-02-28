<?php

namespace NinjaPortal\Mfa\Repositories;

use Illuminate\Contracts\Auth\Authenticatable;
use NinjaPortal\Mfa\Contracts\Repositories\MfaProfileRepositoryInterface;
use NinjaPortal\Mfa\Models\MfaProfile;
use NinjaPortal\Portal\Common\Repositories\BaseRepository;

/**
 * @extends BaseRepository<MfaProfile>
 */
class MfaProfileRepository extends BaseRepository implements MfaProfileRepositoryInterface
{
    public function firstOrCreateForActor(Authenticatable $actor): MfaProfile
    {
        return MfaProfile::query()->firstOrCreate([
            'authenticatable_type' => $actor::class,
            'authenticatable_id' => (int) $actor->getAuthIdentifier(),
        ]);
    }

    public function findForActor(Authenticatable $actor): ?MfaProfile
    {
        $profile = MfaProfile::query()->where([
            'authenticatable_type' => $actor::class,
            'authenticatable_id' => (int) $actor->getAuthIdentifier(),
        ])->first();

        return $profile instanceof MfaProfile ? $profile : null;
    }

    public function updateProfile(MfaProfile $profile, array $attributes): MfaProfile
    {
        $profile->fill($attributes);
        $profile->save();

        return $profile->refresh();
    }
}
