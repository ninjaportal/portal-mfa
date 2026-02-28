<?php

namespace NinjaPortal\Mfa\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use NinjaPortal\Mfa\Contracts\Repositories\MfaFactorRepositoryInterface;
use NinjaPortal\Mfa\Contracts\Repositories\MfaProfileRepositoryInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaActorConfigServiceInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaProfileServiceInterface;
use NinjaPortal\Mfa\Models\MfaFactor;
use NinjaPortal\Mfa\Models\MfaProfile;

class MfaProfileService implements MfaProfileServiceInterface
{
    public function __construct(
        protected MfaProfileRepositoryInterface $profiles,
        protected MfaFactorRepositoryInterface $factors,
        protected MfaActorConfigServiceInterface $actors
    ) {}

    public function getSettingsPayload(Authenticatable $actor, string $context): array
    {
        $profile = $this->profiles->firstOrCreateForActor($actor);
        $factors = $this->factors->listForActor($actor);
        $enabledFactors = $this->enabledFactorsForContext($factors, $context);

        return [
            'context' => $this->normalizeContext($context),
            'actor' => [
                'id' => $actor->getAuthIdentifier(),
                'email' => (string) ($actor->email ?? ''),
            ],
            'profile' => [
                'is_enabled' => (bool) $profile->is_enabled,
                'preferred_driver' => $profile->preferred_driver,
                'effective_required' => $this->requiresMfa($actor, $context),
            ],
            'available_drivers' => $this->actors->actorAllowedDrivers($context),
            'factors' => $factors->map(fn (MfaFactor $factor) => [
                'driver' => $factor->driver,
                'label' => $factor->label,
                'is_enabled' => (bool) $factor->is_enabled,
                'is_verified' => (bool) $factor->is_verified,
                'is_primary' => (bool) $factor->is_primary,
                'verified_at' => optional($factor->verified_at)?->toIso8601String(),
                'last_used_at' => optional($factor->last_used_at)?->toIso8601String(),
            ])->values()->all(),
            'effective' => [
                'should_challenge_on_login' => $this->shouldChallengeOnLogin($actor, $context),
                'enabled_factor_count' => $enabledFactors->count(),
            ],
        ];
    }

    public function updateSettings(Authenticatable $actor, string $context, array $attributes): array
    {
        $context = $this->normalizeContext($context);
        $profile = $this->profiles->firstOrCreateForActor($actor);
        $factors = $this->factors->listForActor($actor);
        $enabledFactors = $this->enabledFactorsForContext($factors, $context);
        $allowedDrivers = $this->actors->actorAllowedDrivers($context);

        $updates = [];

        if (array_key_exists('is_enabled', $attributes)) {
            $requested = (bool) $attributes['is_enabled'];
            if (! $requested && $this->actors->actorRequired($context)) {
                throw ValidationException::withMessages(['is_enabled' => ['MFA is required for this actor.']]);
            }
            if (! $requested && ! $this->actors->actorAllowUserDisable($context)) {
                throw ValidationException::withMessages(['is_enabled' => ['Disabling MFA is not allowed for this actor.']]);
            }
            if ($requested && $enabledFactors->isEmpty()) {
                throw ValidationException::withMessages(['is_enabled' => ['Enable at least one MFA factor first.']]);
            }

            $updates['is_enabled'] = $requested;
        }

        if (array_key_exists('preferred_driver', $attributes)) {
            $preferred = $attributes['preferred_driver'];
            $preferred = is_string($preferred) ? trim($preferred) : null;
            if ($preferred === '') {
                $preferred = null;
            }

            if ($preferred !== null) {
                if (! in_array($preferred, $allowedDrivers, true)) {
                    throw ValidationException::withMessages(['preferred_driver' => ['Driver is not allowed for this actor.']]);
                }

                $factor = $enabledFactors->first(fn (MfaFactor $factor) => $factor->driver === $preferred);
                if (! $factor instanceof MfaFactor) {
                    throw ValidationException::withMessages(['preferred_driver' => ['Selected driver is not enabled for this account.']]);
                }

                $this->factors->clearPrimaryForActor($actor);
                $factor->is_primary = true;
                $this->factors->save($factor);
            }

            $updates['preferred_driver'] = $preferred;
        }

        if ($updates !== []) {
            $this->profiles->updateProfile($profile, $updates);
        }

        return $this->getSettingsPayload($actor, $context);
    }

    public function requiresMfa(Authenticatable $actor, string $context): bool
    {
        $context = $this->normalizeContext($context);
        if (! $this->actors->actorEnabled($context)) {
            return false;
        }

        if ($this->actors->actorRequired($context)) {
            return true;
        }

        $profile = $this->profiles->findForActor($actor);

        return (bool) ($profile?->is_enabled ?? false);
    }

    public function shouldChallengeOnLogin(Authenticatable $actor, string $context): bool
    {
        $context = $this->normalizeContext($context);
        if (! $this->actors->actorEnabled($context)) {
            return false;
        }

        $enabledFactors = $this->enabledFactorsForContext($this->factors->listForActor($actor), $context);
        if ($enabledFactors->isEmpty()) {
            return false;
        }

        if ($this->actors->actorRequired($context)) {
            return true;
        }

        $profile = $this->profiles->findForActor($actor);

        return (bool) ($profile?->is_enabled ?? false);
    }

    /**
     * @param  Collection<int, MfaFactor>  $factors
     * @return Collection<int, MfaFactor>
     */
    protected function enabledFactorsForContext(Collection $factors, string $context): Collection
    {
        $allowed = $this->actors->actorAllowedDrivers($context);

        return $factors->filter(fn (MfaFactor $factor) => $factor->is_enabled && $factor->is_verified && in_array($factor->driver, $allowed, true))->values();
    }

    protected function normalizeContext(string $context): string
    {
        return strtolower(trim($context)) === 'admin' ? 'admin' : 'consumer';
    }
}
