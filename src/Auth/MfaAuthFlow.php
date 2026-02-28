<?php

namespace NinjaPortal\Mfa\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use NinjaPortal\Api\Contracts\Auth\AuthFlowInterface;
use NinjaPortal\Api\Contracts\Auth\TokenServiceInterface;
use NinjaPortal\Api\Events\Auth\LoginAttemptedEvent;
use NinjaPortal\Api\Events\Auth\LoginFailedEvent;
use NinjaPortal\Api\Events\Auth\LoginSucceededEvent;
use NinjaPortal\Api\Support\PortalApiContext;
use NinjaPortal\Mfa\Contracts\Services\MfaChallengeServiceInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaProfileServiceInterface;

class MfaAuthFlow implements AuthFlowInterface
{
    public function __construct(
        protected TokenServiceInterface $tokens,
        protected PortalApiContext $context,
        protected MfaProfileServiceInterface $mfaProfiles,
        protected MfaChallengeServiceInterface $mfaChallenges
    ) {}

    public function attemptLogin(string $email, string $password, string $context): array
    {
        $context = $this->normalizeContext($context);
        $normalizedEmail = trim(strtolower($email));

        Event::dispatch(new LoginAttemptedEvent($context, $normalizedEmail));

        $user = $this->findByEmail($normalizedEmail, $context);
        if (! $user || ! Hash::check($password, (string) ($user->password ?? ''))) {
            Event::dispatch(new LoginFailedEvent($context, $normalizedEmail, 'invalid_credentials'));

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $requiresMfa = $this->mfaProfiles->requiresMfa($user, $context);
        $shouldChallenge = $this->mfaProfiles->shouldChallengeOnLogin($user, $context);

        if ($requiresMfa && ! $shouldChallenge) {
            Event::dispatch(new LoginFailedEvent($context, $normalizedEmail, 'mfa_not_configured'));

            throw ValidationException::withMessages([
                'mfa' => ['MFA is required for this account but no eligible factor is configured.'],
            ]);
        }

        if ($shouldChallenge) {
            try {
                $challenge = $this->mfaChallenges->createLoginChallenge($user, $context);
            } catch (\RuntimeException $e) {
                Event::dispatch(new LoginFailedEvent($context, $normalizedEmail, 'mfa_not_configured'));
                throw ValidationException::withMessages([
                    'mfa' => ['MFA is required for this account but no eligible factor is configured.'],
                ]);
            }

            throw new HttpResponseException(
                response()->jsonResponse(true, 202, 'MFA challenge required.', 'data', $challenge)
            );
        }

        $payload = $this->tokens->issue($user, $context);
        Event::dispatch(new LoginSucceededEvent($context, $normalizedEmail, $user));

        return $payload;
    }

    public function issueForUser(Authenticatable $user, string $context): array
    {
        return $this->tokens->issue($user, $this->normalizeContext($context));
    }

    public function refresh(string $refreshToken, string $context): array
    {
        return $this->tokens->refresh($refreshToken, $this->normalizeContext($context));
    }

    public function logout(string $refreshToken, string $context): void
    {
        $this->tokens->revoke($refreshToken, $this->normalizeContext($context));
    }

    protected function findByEmail(string $email, string $context): ?Authenticatable
    {
        $modelClass = $this->context->modelClassForContext($context);

        /** @var Model|null $user */
        $user = $modelClass::query()->where('email', $email)->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    protected function normalizeContext(string $context): string
    {
        return strtolower(trim($context)) === 'admin' ? 'admin' : 'consumer';
    }
}
