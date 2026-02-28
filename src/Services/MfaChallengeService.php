<?php

namespace NinjaPortal\Mfa\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use NinjaPortal\Api\Contracts\Auth\TokenServiceInterface;
use NinjaPortal\Api\Events\Auth\LoginSucceededEvent;
use NinjaPortal\Mfa\Contracts\Repositories\MfaChallengeRepositoryInterface;
use NinjaPortal\Mfa\Contracts\Repositories\MfaFactorRepositoryInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaActorConfigServiceInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaChallengeServiceInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaDriverManagerInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaProfileServiceInterface;
use NinjaPortal\Mfa\Events\MfaChallengeCreatedEvent;
use NinjaPortal\Mfa\Events\MfaChallengeFailedEvent;
use NinjaPortal\Mfa\Events\MfaChallengeVerifiedEvent;
use NinjaPortal\Mfa\Models\MfaChallenge;
use NinjaPortal\Mfa\Models\MfaFactor;
use NinjaPortal\Mfa\Support\MfaTokenHasher;
use RuntimeException;

class MfaChallengeService implements MfaChallengeServiceInterface
{
    public function __construct(
        protected MfaChallengeRepositoryInterface $challenges,
        protected MfaFactorRepositoryInterface $factors,
        protected MfaDriverManagerInterface $drivers,
        protected MfaProfileServiceInterface $profiles,
        protected MfaActorConfigServiceInterface $actors,
        protected MfaTokenHasher $hasher,
        protected TokenServiceInterface $tokens
    ) {}

    public function createLoginChallenge(Authenticatable $actor, string $context): array
    {
        return $this->createChallengeForPurpose($actor, $context, 'login');
    }

    public function verifyLoginChallenge(string $context, string $challengeToken, string $code): array
    {
        $challenge = $this->resolvePendingChallenge($context, $challengeToken, 'login');
        $actor = $this->resolveChallengeActor($challenge);
        $factor = $challenge->factor;
        if (! $factor instanceof MfaFactor) {
            throw new AuthenticationException('MFA factor not found for challenge.');
        }

        $this->ensureAttemptsRemaining($challenge, 'code');

        $driver = $this->drivers->driver($factor->driver);
        if (! $driver->verifyChallenge($challenge, $factor, $code, $actor, $context)) {
            $challenge->attempts = (int) $challenge->attempts + 1;
            if ((int) $challenge->attempts >= (int) $challenge->max_attempts) {
                $challenge->invalidated_at = now();
            }
            $this->challenges->save($challenge);

            event(new MfaChallengeFailedEvent($context, 'login', $actor, $challenge, 'invalid_code'));

            throw ValidationException::withMessages(['code' => ['Invalid verification code.']]);
        }

        $challenge->completed_at = now();
        $this->challenges->save($challenge);
        $factor->last_used_at = now();
        $factor->save();

        event(new MfaChallengeVerifiedEvent($context, 'login', $actor, $challenge));

        $payload = $this->tokens->issue($actor, $context);
        event(new LoginSucceededEvent($context, strtolower(trim((string) ($actor->email ?? ''))), $actor));

        return $payload;
    }

    public function resendLoginChallenge(string $context, string $challengeToken): array
    {
        $challenge = $this->resolvePendingChallenge($context, $challengeToken, 'login');
        $actor = $this->resolveChallengeActor($challenge);
        $factor = $challenge->factor;
        if (! $factor instanceof MfaFactor) {
            throw new AuthenticationException('MFA factor not found for challenge.');
        }

        $driver = $this->drivers->driver($factor->driver);
        if (! $driver->supportsResend()) {
            throw ValidationException::withMessages(['challenge_token' => ['This MFA driver does not support resending codes.']]);
        }

        $payload = $driver->resendChallenge($challenge, $factor, $actor, $context);
        $payload = $this->sanitizeDriverPayload($payload);
        $payload['challenge_token'] = $challengeToken;

        return $payload;
    }

    public function createFactorChallenge(Authenticatable $actor, string $context, MfaFactor $factor, string $purpose): array
    {
        return $this->createChallengeForPurpose($actor, $context, $purpose, $factor);
    }

    public function verifyFactorChallenge(string $context, string $challengeToken, string $code, string $purpose): MfaChallenge
    {
        $challenge = $this->resolvePendingChallenge($context, $challengeToken, $purpose);
        $actor = $this->resolveChallengeActor($challenge);
        $factor = $challenge->factor;
        if (! $factor instanceof MfaFactor) {
            throw new AuthenticationException('MFA factor not found for challenge.');
        }

        $this->ensureAttemptsRemaining($challenge, 'code');

        $driver = $this->drivers->driver($factor->driver);
        if (! $driver->verifyChallenge($challenge, $factor, $code, $actor, $context)) {
            $challenge->attempts = (int) $challenge->attempts + 1;
            if ((int) $challenge->attempts >= (int) $challenge->max_attempts) {
                $challenge->invalidated_at = now();
            }
            $this->challenges->save($challenge);

            event(new MfaChallengeFailedEvent($context, $purpose, $actor, $challenge, 'invalid_code'));

            throw ValidationException::withMessages(['code' => ['Invalid verification code.']]);
        }

        $challenge->completed_at = now();
        $challenge = $this->challenges->save($challenge);

        event(new MfaChallengeVerifiedEvent($context, $purpose, $actor, $challenge));

        return $challenge;
    }

    protected function createChallengeForPurpose(Authenticatable $actor, string $context, string $purpose, ?MfaFactor $factor = null): array
    {
        $context = $this->normalizeContext($context);
        $factor ??= $this->selectChallengeFactor($actor, $context);

        if (! $factor instanceof MfaFactor) {
            throw new RuntimeException('No eligible MFA factor is available for this account.');
        }

        $driver = $this->drivers->driver($factor->driver);
        $token = $this->hasher->makeToken((int) config('portal-mfa.challenge.token_length', 64));
        $ttl = (int) config('portal-mfa.challenge.login_ttl_seconds', 300);
        $maxAttempts = $factor->driver === 'email_otp'
            ? (int) config('portal-mfa.drivers.email_otp.max_attempts', 5)
            : 5;
        $maxResends = $factor->driver === 'email_otp'
            ? (int) config('portal-mfa.drivers.email_otp.max_resends', 3)
            : 0;

        $this->challenges->invalidateOpenChallengesForActorDriver($actor, $factor->driver, $purpose);

        $challenge = $this->challenges->createForActor($actor, $factor, [
            'token_hash' => $this->hasher->hash($token),
            'context' => $context,
            'purpose' => $purpose,
            'expires_at' => now()->addSeconds(max(30, $ttl)),
            'max_attempts' => max(1, $maxAttempts),
            'max_resends' => max(0, $maxResends),
            'payload' => [],
        ]);

        try {
            $driverPayload = $driver->prepareChallenge($challenge, $factor, $actor, $context);
        } catch (\Throwable $e) {
            $challenge->invalidated_at = now();
            $this->challenges->save($challenge);

            throw $e;
        }

        $driverPayload = $this->sanitizeDriverPayload($driverPayload);
        $clientPayload = array_merge([
            'mfa_required' => true,
            'challenge_type' => $purpose,
            'challenge_token' => $token,
        ], $driverPayload);

        event(new MfaChallengeCreatedEvent($context, $purpose, $actor, $challenge, $clientPayload));

        return $clientPayload;
    }

    protected function selectChallengeFactor(Authenticatable $actor, string $context): ?MfaFactor
    {
        $allowedDrivers = $this->actors->actorAllowedDrivers($context);
        $all = $this->factors->listForActor($actor)
            ->filter(fn (MfaFactor $factor) => $factor->is_enabled && $factor->is_verified && in_array($factor->driver, $allowedDrivers, true))
            ->values();

        if ($all->isEmpty()) {
            return null;
        }

        $primary = $all->first(fn (MfaFactor $factor) => (bool) $factor->is_primary);
        if ($primary instanceof MfaFactor) {
            return $primary;
        }

        $profile = $this->profiles->getSettingsPayload($actor, $context);
        $preferredDriver = data_get($profile, 'profile.preferred_driver');
        if (is_string($preferredDriver) && $preferredDriver !== '') {
            $preferred = $all->first(fn (MfaFactor $factor) => $factor->driver === $preferredDriver);
            if ($preferred instanceof MfaFactor) {
                return $preferred;
            }
        }

        $ordered = collect($allowedDrivers)
            ->map(fn (string $driverKey) => $all->first(fn (MfaFactor $factor) => $factor->driver === $driverKey))
            ->first(fn ($factor) => $factor instanceof MfaFactor);

        return $ordered instanceof MfaFactor ? $ordered : $all->first();
    }

    protected function resolvePendingChallenge(string $context, string $token, string $purpose): MfaChallenge
    {
        $tokenHash = $this->hasher->hash(trim($token));
        $challenge = $this->challenges->findPendingByTokenHash($tokenHash, $this->normalizeContext($context), $purpose);
        if (! $challenge instanceof MfaChallenge) {
            throw ValidationException::withMessages(['challenge_token' => ['Invalid or expired MFA challenge.']]);
        }

        $challenge->loadMissing('factor', 'authenticatable');

        return $challenge;
    }

    protected function resolveChallengeActor(MfaChallenge $challenge): Authenticatable
    {
        $actor = $challenge->authenticatable;
        if (! $actor instanceof Authenticatable) {
            throw new AuthenticationException('Challenge actor could not be resolved.');
        }

        return $actor;
    }

    protected function ensureAttemptsRemaining(MfaChallenge $challenge, string $field): void
    {
        if ((int) $challenge->attempts >= (int) $challenge->max_attempts) {
            throw ValidationException::withMessages([$field => ['Maximum verification attempts reached.']]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizeDriverPayload(array $payload): array
    {
        unset($payload['_internal_plain_code']);

        return $payload;
    }

    protected function normalizeContext(string $context): string
    {
        return strtolower(trim($context)) === 'admin' ? 'admin' : 'consumer';
    }
}
