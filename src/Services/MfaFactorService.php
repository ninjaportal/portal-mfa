<?php

namespace NinjaPortal\Mfa\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use NinjaPortal\Mfa\Contracts\Drivers\EnrollsMfaFactorInterface;
use NinjaPortal\Mfa\Contracts\Repositories\MfaFactorRepositoryInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaActorConfigServiceInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaChallengeServiceInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaDriverManagerInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaFactorServiceInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaProfileServiceInterface;
use NinjaPortal\Mfa\Events\MfaFactorDisabledEvent;
use NinjaPortal\Mfa\Events\MfaFactorEnabledEvent;
use NinjaPortal\Mfa\Models\MfaFactor;
use RuntimeException;

class MfaFactorService implements MfaFactorServiceInterface
{
    public function __construct(
        protected MfaFactorRepositoryInterface $factors,
        protected MfaDriverManagerInterface $drivers,
        protected MfaChallengeServiceInterface $challenges,
        protected MfaProfileServiceInterface $profiles,
        protected MfaActorConfigServiceInterface $actors
    ) {}

    public function beginAuthenticatorEnrollment(Authenticatable $actor, string $context, ?string $label = null): array
    {
        $context = $this->normalizeContext($context);
        $this->assertDriverAllowed($context, 'authenticator');

        $factor = $this->factors->firstOrNewByActorAndDriver($actor, 'authenticator');
        $driver = $this->drivers->driver('authenticator');
        if (! $driver instanceof EnrollsMfaFactorInterface) {
            throw new RuntimeException('Authenticator driver does not support enrollment.');
        }

        $result = $driver->beginEnrollment($actor, $context, $factor, ['label' => $label]);
        $factor = $this->factors->save($result['factor']);

        return [
            'driver' => 'authenticator',
            'factor' => $this->factorPayload($factor),
            'setup' => $result['payload'],
        ];
    }

    public function confirmAuthenticatorEnrollment(Authenticatable $actor, string $context, string $code): array
    {
        $context = $this->normalizeContext($context);
        $factor = $this->factors->findByActorAndDriver($actor, 'authenticator');
        if (! $factor instanceof MfaFactor) {
            throw ValidationException::withMessages(['driver' => ['Authenticator setup has not been started.']]);
        }

        $driver = $this->drivers->driver('authenticator');
        if (! $driver instanceof EnrollsMfaFactorInterface) {
            throw new RuntimeException('Authenticator driver does not support enrollment confirmation.');
        }

        $factor = $driver->confirmEnrollment($actor, $context, $factor, ['code' => $code]);
        $this->promoteAsPrimaryIfNeeded($actor, $context, $factor);
        $factor = $this->factors->save($factor);

        event(new MfaFactorEnabledEvent($context, $actor, $factor));

        return [
            'driver' => 'authenticator',
            'factor' => $this->factorPayload($factor),
            'settings' => $this->profiles->getSettingsPayload($actor, $context),
        ];
    }

    public function beginEmailOtpEnrollment(Authenticatable $actor, string $context): array
    {
        $context = $this->normalizeContext($context);
        $this->assertDriverAllowed($context, 'email_otp');

        $factor = $this->factors->firstOrNewByActorAndDriver($actor, 'email_otp');
        $factor->label = trim((string) ($actor->email ?? '')) ?: 'Email OTP';
        $factor->is_enabled = false;
        $factor->is_verified = false;
        $factor = $this->factors->save($factor);

        $challenge = $this->challenges->createFactorChallenge($actor, $context, $factor, 'factor_email_enrollment');

        return [
            'driver' => 'email_otp',
            'challenge' => $challenge,
        ];
    }

    public function confirmEmailOtpEnrollment(Authenticatable $actor, string $context, string $challengeToken, string $code): array
    {
        $context = $this->normalizeContext($context);
        $challenge = $this->challenges->verifyFactorChallenge($context, $challengeToken, $code, 'factor_email_enrollment');
        $challengeActor = $challenge->authenticatable;

        if (! $challengeActor instanceof Authenticatable || $challengeActor::class !== $actor::class || (string) $challengeActor->getAuthIdentifier() !== (string) $actor->getAuthIdentifier()) {
            throw ValidationException::withMessages(['challenge_token' => ['Challenge does not belong to the authenticated user.']]);
        }

        $factor = $challenge->factor;
        if (! $factor instanceof MfaFactor) {
            throw ValidationException::withMessages(['driver' => ['Email OTP factor could not be resolved.']]);
        }

        $factor->is_verified = true;
        $factor->is_enabled = true;
        $factor->verified_at = now();
        $this->promoteAsPrimaryIfNeeded($actor, $context, $factor);
        $factor = $this->factors->save($factor);

        event(new MfaFactorEnabledEvent($context, $actor, $factor));

        return [
            'driver' => 'email_otp',
            'factor' => $this->factorPayload($factor),
            'settings' => $this->profiles->getSettingsPayload($actor, $context),
        ];
    }

    public function disableFactor(Authenticatable $actor, string $context, string $driver): void
    {
        $context = $this->normalizeContext($context);
        $this->assertDriverAllowed($context, $driver);
        $factor = $this->factors->findByActorAndDriver($actor, $driver);
        if (! $factor instanceof MfaFactor) {
            return;
        }

        $settings = $this->profiles->getSettingsPayload($actor, $context);
        $isRequired = (bool) data_get($settings, 'profile.effective_required', false);
        $enabledFactors = collect((array) data_get($settings, 'factors', []))->filter(fn ($item) => (bool) data_get($item, 'is_enabled', false));
        if ($isRequired && $enabledFactors->count() <= 1 && (bool) $factor->is_enabled) {
            throw ValidationException::withMessages(['driver' => ['Cannot disable the last enabled MFA factor while MFA is required.']]);
        }

        $factor->is_enabled = false;
        $factor->is_primary = false;
        $factor = $this->factors->save($factor);

        event(new MfaFactorDisabledEvent($context, $actor, $factor));
    }

    protected function promoteAsPrimaryIfNeeded(Authenticatable $actor, string $context, MfaFactor $factor): void
    {
        $settings = $this->profiles->getSettingsPayload($actor, $context);
        $preferred = data_get($settings, 'profile.preferred_driver');
        $hasAnyPrimary = collect((array) data_get($settings, 'factors', []))->contains(fn ($item) => (bool) data_get($item, 'is_primary', false));

        if ($preferred === $factor->driver || ! $hasAnyPrimary) {
            $this->factors->clearPrimaryForActor($actor);
            $factor->is_primary = true;
        }
    }

    protected function factorPayload(MfaFactor $factor): array
    {
        return [
            'driver' => $factor->driver,
            'label' => $factor->label,
            'is_enabled' => (bool) $factor->is_enabled,
            'is_verified' => (bool) $factor->is_verified,
            'is_primary' => (bool) $factor->is_primary,
            'verified_at' => optional($factor->verified_at)?->toIso8601String(),
            'last_used_at' => optional($factor->last_used_at)?->toIso8601String(),
        ];
    }

    protected function assertDriverAllowed(string $context, string $driver): void
    {
        if (! in_array($driver, $this->actors->actorAllowedDrivers($context), true)) {
            throw ValidationException::withMessages(['driver' => ['Driver is not allowed for this actor.']]);
        }
    }

    protected function normalizeContext(string $context): string
    {
        return strtolower(trim($context)) === 'admin' ? 'admin' : 'consumer';
    }
}
