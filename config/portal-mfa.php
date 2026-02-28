<?php

use NinjaPortal\Mfa\Drivers\AuthenticatorAppDriver;
use NinjaPortal\Mfa\Drivers\EmailOtpDriver;
use NinjaPortal\Mfa\Notifications\EmailOtpCodeNotification;

return [
    'enabled' => env('PORTAL_MFA_ENABLED', true),

    'actors' => [
        'consumer' => [
            'enabled' => env('PORTAL_MFA_CONSUMER_ENABLED', true),
            'required' => env('PORTAL_MFA_CONSUMER_REQUIRED', false),
            'allow_user_disable' => env('PORTAL_MFA_CONSUMER_ALLOW_DISABLE', true),
            'allowed_drivers' => ['authenticator', 'email_otp'],
            'default_driver' => env('PORTAL_MFA_CONSUMER_DEFAULT_DRIVER', 'authenticator'),
        ],
        'admin' => [
            'enabled' => env('PORTAL_MFA_ADMIN_ENABLED', true),
            'required' => env('PORTAL_MFA_ADMIN_REQUIRED', false),
            'allow_user_disable' => env('PORTAL_MFA_ADMIN_ALLOW_DISABLE', true),
            'allowed_drivers' => ['authenticator', 'email_otp'],
            'default_driver' => env('PORTAL_MFA_ADMIN_DEFAULT_DRIVER', 'authenticator'),
        ],
    ],

    'drivers' => [
        'map' => [
            'authenticator' => AuthenticatorAppDriver::class,
            'email_otp' => EmailOtpDriver::class,
        ],

        'authenticator' => [
            'issuer' => env('PORTAL_MFA_AUTHENTICATOR_ISSUER', env('APP_NAME', 'NinjaPortal')),
            'digits' => (int) env('PORTAL_MFA_TOTP_DIGITS', 6),
            'period' => (int) env('PORTAL_MFA_TOTP_PERIOD', 30),
            'window' => (int) env('PORTAL_MFA_TOTP_WINDOW', 1),
            'secret_length' => (int) env('PORTAL_MFA_TOTP_SECRET_LENGTH', 20),
        ],

        'email_otp' => [
            'digits' => (int) env('PORTAL_MFA_EMAIL_OTP_DIGITS', 6),
            'ttl_seconds' => (int) env('PORTAL_MFA_EMAIL_OTP_TTL', 300),
            'resend_cooldown_seconds' => (int) env('PORTAL_MFA_EMAIL_OTP_RESEND_COOLDOWN', 30),
            'max_attempts' => (int) env('PORTAL_MFA_EMAIL_OTP_MAX_ATTEMPTS', 5),
            'max_resends' => (int) env('PORTAL_MFA_EMAIL_OTP_MAX_RESENDS', 3),
            'notification' => EmailOtpCodeNotification::class,
            'mailer' => env('PORTAL_MFA_EMAIL_MAILER'),
        ],
    ],

    'challenge' => [
        'token_length' => (int) env('PORTAL_MFA_CHALLENGE_TOKEN_LENGTH', 64),
        'login_ttl_seconds' => (int) env('PORTAL_MFA_LOGIN_CHALLENGE_TTL', 300),
        'prune_after_days' => (int) env('PORTAL_MFA_CHALLENGE_PRUNE_AFTER_DAYS', 7),
    ],

    'routes' => [
        'enabled' => env('PORTAL_MFA_ROUTES_ENABLED', true),
    ],
];
