<?php

namespace NinjaPortal\Mfa\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use NinjaPortal\Mfa\Contracts\Drivers\MfaDriverInterface;
use NinjaPortal\Mfa\Events\MfaOtpSentEvent;
use NinjaPortal\Mfa\Models\MfaChallenge;
use NinjaPortal\Mfa\Models\MfaFactor;
use NinjaPortal\Mfa\Notifications\EmailOtpCodeNotification;
use NinjaPortal\Mfa\Support\MfaMask;
use RuntimeException;

class EmailOtpDriver implements MfaDriverInterface
{
    public function __construct(protected MfaMask $mask) {}

    public function key(): string
    {
        return 'email_otp';
    }

    public function supportsResend(): bool
    {
        return true;
    }

    public function prepareChallenge(MfaChallenge $challenge, MfaFactor $factor, Authenticatable $actor, string $context): array
    {
        $this->issueCode($challenge, $actor, $context);

        return $this->challengePayload($challenge, $context);
    }

    public function verifyChallenge(MfaChallenge $challenge, MfaFactor $factor, string $code, Authenticatable $actor, string $context): bool
    {
        $normalized = preg_replace('/\D+/', '', $code) ?? '';
        $hash = (string) ($challenge->code_hash ?? '');

        return $normalized !== '' && $hash !== '' && hash_equals($hash, hash('sha256', $normalized));
    }

    public function resendChallenge(MfaChallenge $challenge, MfaFactor $factor, Authenticatable $actor, string $context): array
    {
        $cooldownSeconds = (int) config('portal-mfa.drivers.email_otp.resend_cooldown_seconds', 30);
        if ($challenge->last_sent_at && $challenge->last_sent_at->gt(now()->subSeconds($cooldownSeconds))) {
            throw ValidationException::withMessages([
                'challenge_token' => ['Please wait before requesting another code.'],
            ]);
        }

        if ((int) $challenge->resend_count >= (int) $challenge->max_resends) {
            throw ValidationException::withMessages([
                'challenge_token' => ['Maximum resend attempts reached for this challenge.'],
            ]);
        }

        $challenge->resend_count = (int) $challenge->resend_count + 1;
        $challenge->expires_at = now()->addSeconds((int) config('portal-mfa.drivers.email_otp.ttl_seconds', 300));
        $challenge->save();

        $this->issueCode($challenge, $actor, $context);

        return $this->challengePayload($challenge, $context);
    }

    protected function issueCode(MfaChallenge $challenge, Authenticatable $actor, string $context): void
    {
        $digits = max(4, (int) config('portal-mfa.drivers.email_otp.digits', 6));
        $code = str_pad((string) random_int(0, (10 ** $digits) - 1), $digits, '0', STR_PAD_LEFT);
        $challenge->code_hash = hash('sha256', $code);
        $challenge->last_sent_at = now();
        $challenge->payload = array_merge((array) $challenge->payload, [
            'masked_destination' => $this->mask->email((string) ($actor->email ?? '')),
        ]);
        $challenge->save();

        $this->sendCodeNotification($actor, $code, (int) config('portal-mfa.drivers.email_otp.ttl_seconds', 300), (string) $challenge->purpose);

        event(new MfaOtpSentEvent($context, (string) $challenge->purpose, $actor, $challenge, $this->key()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function challengePayload(MfaChallenge $challenge, string $context): array
    {
        return [
            'driver' => $this->key(),
            'context' => $context,
            'purpose' => $challenge->purpose,
            'expires_at' => optional($challenge->expires_at)?->toIso8601String(),
            'can_resend' => true,
            'masked_destination' => data_get($challenge->payload, 'masked_destination'),
            'resend_count' => (int) $challenge->resend_count,
            'max_resends' => (int) $challenge->max_resends,
            'sent_at' => optional($challenge->last_sent_at)?->toIso8601String(),
            'delivery' => ['channel' => 'email'],
        ];
    }

    protected function sendCodeNotification(Authenticatable $actor, string $code, int $ttlSeconds, string $purpose): void
    {
        $email = trim((string) ($actor->email ?? ''));
        if ($email === '') {
            throw new RuntimeException('Cannot send email OTP: actor does not have an email address.');
        }

        $notificationClass = (string) config('portal-mfa.drivers.email_otp.notification', EmailOtpCodeNotification::class);
        $mailer = config('portal-mfa.drivers.email_otp.mailer');
        /** @var \Illuminate\Notifications\Notification $notification */
        $notification = new $notificationClass($code, $ttlSeconds, $purpose, is_string($mailer) ? $mailer : null);

        Notification::route('mail', $email)->notify($notification);
    }
}
