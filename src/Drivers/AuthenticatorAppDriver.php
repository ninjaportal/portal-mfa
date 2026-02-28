<?php

namespace NinjaPortal\Mfa\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use NinjaPortal\Mfa\Contracts\Drivers\EnrollsMfaFactorInterface;
use NinjaPortal\Mfa\Contracts\Drivers\MfaDriverInterface;
use NinjaPortal\Mfa\Models\MfaChallenge;
use NinjaPortal\Mfa\Models\MfaFactor;
use NinjaPortal\Mfa\Support\TotpService;
use RuntimeException;

class AuthenticatorAppDriver implements MfaDriverInterface, EnrollsMfaFactorInterface
{
    public function __construct(protected TotpService $totp) {}

    public function key(): string
    {
        return 'authenticator';
    }

    public function supportsResend(): bool
    {
        return false;
    }

    public function prepareChallenge(MfaChallenge $challenge, MfaFactor $factor, Authenticatable $actor, string $context): array
    {
        return [
            'driver' => $this->key(),
            'context' => $context,
            'purpose' => $challenge->purpose,
            'expires_at' => optional($challenge->expires_at)?->toIso8601String(),
            'can_resend' => false,
            'prompt' => 'Enter the code from your authenticator app.',
        ];
    }

    public function verifyChallenge(MfaChallenge $challenge, MfaFactor $factor, string $code, Authenticatable $actor, string $context): bool
    {
        return $this->totp->verifyCode(
            $this->decryptSecret($factor),
            $code,
            (int) config('portal-mfa.drivers.authenticator.window', 1),
            (int) config('portal-mfa.drivers.authenticator.period', 30),
            (int) config('portal-mfa.drivers.authenticator.digits', 6),
        );
    }

    public function resendChallenge(MfaChallenge $challenge, MfaFactor $factor, Authenticatable $actor, string $context): array
    {
        throw new RuntimeException('Authenticator app challenges do not support resend.');
    }

    public function beginEnrollment(Authenticatable $actor, string $context, MfaFactor $factor, array $input = []): array
    {
        $secret = $this->totp->generateSecret((int) config('portal-mfa.drivers.authenticator.secret_length', 20));
        $email = trim((string) ($actor->email ?? ''));
        $issuer = (string) config('portal-mfa.drivers.authenticator.issuer', config('app.name', 'NinjaPortal'));
        $digits = (int) config('portal-mfa.drivers.authenticator.digits', 6);
        $period = (int) config('portal-mfa.drivers.authenticator.period', 30);
        $factor->label = trim((string) ($input['label'] ?? $factor->label ?? '')) ?: ($email !== '' ? $email : 'Authenticator App');
        $factor->secret_encrypted = Crypt::encryptString($secret);
        $factor->is_verified = false;
        $factor->is_enabled = false;
        $factor->config = array_merge((array) $factor->config, [
            'issuer' => $issuer,
            'digits' => $digits,
            'period' => $period,
            'context' => $context,
        ]);

        $account = $email !== '' ? $email : (string) $actor->getAuthIdentifier();

        return [
            'factor' => $factor,
            'payload' => [
                'driver' => $this->key(),
                'secret' => $secret,
                'issuer' => $issuer,
                'account_label' => $account,
                'digits' => $digits,
                'period' => $period,
                'otpauth_uri' => $this->totp->buildOtpAuthUri($secret, $issuer, $account, $digits, $period),
            ],
        ];
    }

    public function confirmEnrollment(Authenticatable $actor, string $context, MfaFactor $factor, array $input = []): MfaFactor
    {
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            throw ValidationException::withMessages(['code' => ['Verification code is required.']]);
        }

        if (! $this->verifyChallenge(new MfaChallenge(['purpose' => 'factor_authenticator_enrollment']), $factor, $code, $actor, $context)) {
            throw ValidationException::withMessages(['code' => ['Invalid authenticator code.']]);
        }

        $factor->is_verified = true;
        $factor->is_enabled = true;
        $factor->verified_at = now();

        return $factor;
    }

    protected function decryptSecret(MfaFactor $factor): string
    {
        $encrypted = (string) ($factor->secret_encrypted ?? '');
        if ($encrypted === '') {
            throw new RuntimeException('Authenticator factor secret is missing.');
        }

        return Crypt::decryptString($encrypted);
    }
}
