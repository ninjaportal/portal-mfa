<?php

namespace NinjaPortal\Mfa\Http\Controllers\V1\User;

use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Support\PortalApiContext;
use NinjaPortal\Mfa\Contracts\Services\MfaFactorServiceInterface;
use NinjaPortal\Mfa\Contracts\Services\MfaProfileServiceInterface;
use NinjaPortal\Mfa\Http\Controllers\Concerns\ResolvesMfaActor;
use NinjaPortal\Mfa\Http\Requests\User\BeginAuthenticatorEnrollmentRequest;
use NinjaPortal\Mfa\Http\Requests\User\ConfirmAuthenticatorEnrollmentRequest;
use NinjaPortal\Mfa\Http\Requests\User\ConfirmEmailOtpEnrollmentRequest;
use NinjaPortal\Mfa\Http\Requests\User\UpdateMfaSettingsRequest;

/**
 * @group MFA (Consumer)
 *
 * Manage consumer MFA settings and factors for the currently authenticated user.
 */
class MfaSettingsController extends Controller
{
    use ResolvesMfaActor;

    public function __construct(
        protected MfaProfileServiceInterface $profiles,
        protected MfaFactorServiceInterface $factors,
        protected PortalApiContext $context
    ) {}

    /**
     * Get MFA settings (consumer)
     *
     * Returns the current user's MFA profile state and configured factors.
     *
     * @authenticated
     *
     * @response 200 {"success":true,"status":200,"message":"","data":{"context":"consumer","actor":{"id":1,"email":"user@example.com"},"profile":{"is_enabled":true,"preferred_driver":"authenticator","effective_required":false},"available_drivers":["authenticator","email_otp"],"factors":[{"driver":"authenticator","label":"user@example.com","is_enabled":true,"is_verified":true,"is_primary":true,"verified_at":"2026-02-24T20:10:00Z","last_used_at":"2026-02-24T20:20:00Z"}],"effective":{"should_challenge_on_login":true,"enabled_factor_count":1}},"meta":null}
     */
    public function show(Request $request)
    {
        $actor = $this->authenticatedActor($request, $this->context, 'consumer');

        return response()->success($this->profiles->getSettingsPayload($actor, 'consumer'));
    }

    /**
     * Update MFA settings (consumer)
     *
     * Toggle MFA and/or choose the preferred MFA driver for the current user.
     *
     * @authenticated
     * @bodyParam is_enabled boolean Optional. Enable or disable MFA for this account. Example: true
     * @bodyParam preferred_driver string Optional. Preferred driver when multiple factors are enabled. Example: authenticator
     *
     * @response 200 {"success":true,"status":200,"message":"MFA settings updated.","data":{"context":"consumer","profile":{"is_enabled":true,"preferred_driver":"authenticator","effective_required":false}},"meta":null}
     * @response 422 {"success":false,"status":422,"message":"Validation failed.","errors":{"is_enabled":["Enable at least one MFA factor first."]},"meta":null}
     */
    public function update(UpdateMfaSettingsRequest $request)
    {
        $actor = $this->authenticatedActor($request, $this->context, 'consumer');

        return response()->success('MFA settings updated.', $this->profiles->updateSettings($actor, 'consumer', $request->validated()));
    }

    /**
     * Begin authenticator app setup (consumer)
     *
     * Generates TOTP setup data (`secret` and `otpauth_uri`) for the current user.
     *
     * @authenticated
     * @bodyParam label string Optional display label for this factor. Example: My Phone
     *
     * @response 200 {"success":true,"status":200,"message":"Authenticator setup generated.","data":{"driver":"authenticator","factor":{"driver":"authenticator","label":"My Phone","is_enabled":false,"is_verified":false,"is_primary":false,"verified_at":null,"last_used_at":null},"setup":{"driver":"authenticator","secret":"JBSWY3DPEHPK3PXP","issuer":"NinjaPortal","account_label":"user@example.com","digits":6,"period":30,"otpauth_uri":"otpauth://totp/NinjaPortal%3Auser%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=NinjaPortal&digits=6&period=30"}},"meta":null}
     */
    public function beginAuthenticator(BeginAuthenticatorEnrollmentRequest $request)
    {
        $actor = $this->authenticatedActor($request, $this->context, 'consumer');

        return response()->success('Authenticator setup generated.', $this->factors->beginAuthenticatorEnrollment(
            $actor,
            'consumer',
            $request->validated('label')
        ));
    }

    /**
     * Confirm authenticator app setup (consumer)
     *
     * Verifies the authenticator code and enables the authenticator factor.
     *
     * @authenticated
     * @bodyParam code string required Current code from the authenticator app. Example: 123456
     *
     * @response 200 {"success":true,"status":200,"message":"Authenticator factor enabled.","data":{"driver":"authenticator","factor":{"driver":"authenticator","label":"user@example.com","is_enabled":true,"is_verified":true,"is_primary":true,"verified_at":"2026-02-24T20:10:00Z","last_used_at":null},"settings":{"context":"consumer"}},"meta":null}
     * @response 422 {"success":false,"status":422,"message":"Validation failed.","errors":{"code":["Invalid authenticator code."]},"meta":null}
     */
    public function confirmAuthenticator(ConfirmAuthenticatorEnrollmentRequest $request)
    {
        $actor = $this->authenticatedActor($request, $this->context, 'consumer');

        return response()->success('Authenticator factor enabled.', $this->factors->confirmAuthenticatorEnrollment(
            $actor,
            'consumer',
            (string) $request->validated('code')
        ));
    }

    /**
     * Disable authenticator app factor (consumer)
     *
     * @authenticated
     *
     * @response 200 {"success":true,"status":200,"message":"Authenticator factor disabled.","data":{},"meta":null}
     */
    public function disableAuthenticator(Request $request)
    {
        $actor = $this->authenticatedActor($request, $this->context, 'consumer');
        $this->factors->disableFactor($actor, 'consumer', 'authenticator');

        return response()->success('Authenticator factor disabled.');
    }

    /**
     * Begin email OTP setup (consumer)
     *
     * Sends a verification code to the current user's email to confirm the email OTP factor.
     *
     * @authenticated
     *
     * @response 200 {"success":true,"status":200,"message":"Email OTP verification code sent.","data":{"driver":"email_otp","challenge":{"mfa_required":true,"challenge_type":"factor_email_enrollment","challenge_token":"<token>","driver":"email_otp","context":"consumer","purpose":"factor_email_enrollment","expires_at":"2026-02-24T20:30:00Z","can_resend":true,"masked_destination":"j***e@example.com","resend_count":0,"max_resends":3,"sent_at":"2026-02-24T20:25:00Z","delivery":{"channel":"email"}}},"meta":null}
     */
    public function beginEmailOtp(Request $request)
    {
        $actor = $this->authenticatedActor($request, $this->context, 'consumer');

        return response()->success('Email OTP verification code sent.', $this->factors->beginEmailOtpEnrollment($actor, 'consumer'));
    }

    /**
     * Confirm email OTP setup (consumer)
     *
     * Verifies the emailed code and enables the email OTP factor.
     *
     * @authenticated
     * @bodyParam challenge_token string required Challenge token returned from the email OTP start endpoint. Example: 5d2c9e8f4b6a1d3c7e9f0a2b4c6d8e0f5a1b3c7d9e2f4a6c8b0d1e3f5a7c9e1
     * @bodyParam code string required Code received by email. Example: 123456
     *
     * @response 200 {"success":true,"status":200,"message":"Email OTP factor enabled.","data":{"driver":"email_otp","factor":{"driver":"email_otp","label":"user@example.com","is_enabled":true,"is_verified":true,"is_primary":true,"verified_at":"2026-02-24T20:10:00Z","last_used_at":null},"settings":{"context":"consumer"}},"meta":null}
     * @response 422 {"success":false,"status":422,"message":"Validation failed.","errors":{"challenge_token":["Invalid or expired MFA challenge."]},"meta":null}
     */
    public function confirmEmailOtp(ConfirmEmailOtpEnrollmentRequest $request)
    {
        $actor = $this->authenticatedActor($request, $this->context, 'consumer');
        $data = $request->validated();

        return response()->success('Email OTP factor enabled.', $this->factors->confirmEmailOtpEnrollment(
            $actor,
            'consumer',
            (string) $data['challenge_token'],
            (string) $data['code']
        ));
    }

    /**
     * Disable email OTP factor (consumer)
     *
     * @authenticated
     *
     * @response 200 {"success":true,"status":200,"message":"Email OTP factor disabled.","data":{},"meta":null}
     */
    public function disableEmailOtp(Request $request)
    {
        $actor = $this->authenticatedActor($request, $this->context, 'consumer');
        $this->factors->disableFactor($actor, 'consumer', 'email_otp');

        return response()->success('Email OTP factor disabled.');
    }
}
