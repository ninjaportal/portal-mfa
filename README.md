# NinjaPortal Portal MFA

Configurable multi-factor authentication package for NinjaPortal (`portal` + `portal-api`).

## Features

- MFA for all actors (`consumer`, `admin`) with per-actor config
- Drivers:
  - Authenticator app (TOTP)
  - Email OTP
- Per-user MFA settings and factor management endpoints
- Extensible driver registry for custom MFA drivers
- Integrates with `portal-api` auth flow through `AuthFlowInterface`

## Installation

```bash
composer require ninjaportal/portal-mfa
php artisan migrate
```

The package auto-discovers its service provider and overrides `portal-api`'s `AuthFlowInterface`
binding to insert an MFA challenge step before token issuance.

## Configuration

Publish config (optional):

```bash
php artisan vendor:publish --tag=portal-mfa-config
```

Main config file: `config/portal-mfa.php`

Key areas:
- `enabled`: enable/disable MFA package globally
- `actors.*`: per-actor MFA policy (`enabled`, `required`, allowed drivers)
- `drivers.map`: register built-in or custom drivers
- `drivers.authenticator`: TOTP issuer/window/period/digits
- `drivers.email_otp`: OTP TTL/resend/attempt/notification settings
- `challenge.*`: challenge token length and pruning retention

## Extending Drivers

Register custom driver classes in `portal-mfa.drivers.map`.
Each driver must implement `NinjaPortal\Mfa\Contracts\Drivers\MfaDriverInterface`.
Drivers that support factor setup flows should also implement
`NinjaPortal\Mfa\Contracts\Drivers\EnrollsMfaFactorInterface`.

## Login Flow (MFA Challenge)

1. Password is validated by the MFA auth-flow decorator.
2. If MFA is not required/enabled for the actor/account, tokens are issued normally.
3. If MFA is required/enabled, login returns HTTP `202` with an MFA challenge payload.
4. Client verifies the challenge using the package endpoint.
5. Tokens are issued after successful MFA verification.

Example `202` challenge response:

```json
{
  "success": true,
  "status": 202,
  "message": "MFA challenge required.",
  "data": {
    "mfa_required": true,
    "challenge_type": "login",
    "challenge_token": "<opaque-token>",
    "driver": "email_otp",
    "context": "consumer",
    "purpose": "login",
    "expires_at": "2026-02-24T20:30:00Z",
    "can_resend": true,
    "masked_destination": "j***e@example.com"
  },
  "meta": null
}
```

## API Endpoints (provided by this package)

- Consumer login challenge verify/resend
- Admin login challenge verify/resend
- Consumer `/me/mfa/*` settings and factor management
- Admin `/admin/me/mfa/*` settings and factor management

## Commands

- `php artisan portal-mfa:challenges:prune`
