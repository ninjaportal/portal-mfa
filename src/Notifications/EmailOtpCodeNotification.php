<?php

namespace NinjaPortal\Mfa\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailOtpCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $code,
        protected int $ttlSeconds,
        protected string $purpose = 'login',
        protected ?string $mailer = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = max(1, (int) ceil($this->ttlSeconds / 60));
        $subject = $this->purpose === 'login'
            ? 'Your NinjaPortal login verification code'
            : 'Confirm your MFA email OTP setup';

        $message = (new MailMessage)
            ->subject($subject)
            ->line('Use the following one-time code to continue:')
            ->line(sprintf('**%s**', $this->code))
            ->line(sprintf('This code expires in %d minute(s).', $minutes))
            ->line('If you did not request this, you can ignore this email.');

        if (is_string($this->mailer) && trim($this->mailer) !== '' && method_exists($message, 'mailer')) {
            $message->mailer($this->mailer);
        }

        return $message;
    }
}
