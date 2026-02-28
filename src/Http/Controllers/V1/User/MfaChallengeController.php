<?php

namespace NinjaPortal\Mfa\Http\Controllers\V1\User;

use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Mfa\Contracts\Services\MfaChallengeServiceInterface;
use NinjaPortal\Mfa\Http\Requests\User\ResendMfaChallengeRequest;
use NinjaPortal\Mfa\Http\Requests\User\VerifyMfaChallengeRequest;

/**
 * @group MFA (Consumer)
 *
 * Consumer MFA challenge endpoints used after password login returns an MFA challenge.
 */
class MfaChallengeController extends Controller
{
    public function __construct(protected MfaChallengeServiceInterface $challenges) {}

    /**
     * Verify login MFA challenge (consumer)
     *
     * Completes a pending consumer login MFA challenge and issues tokens on success.
     *
     * @bodyParam challenge_token string required Opaque MFA challenge token returned from the login `202` response. Example: 5d2c9e8f4b6a1d3c7e9f0a2b4c6d8e0f5a1b3c7d9e2f4a6c8b0d1e3f5a7c9e1
     * @bodyParam code string required MFA verification code (TOTP or email OTP). Example: 123456
     *
     * @response 200 {"success":true,"status":200,"message":"MFA verification succeeded.","data":{"token_type":"Bearer","access_token":"<jwt>","expires_in":900,"refresh_token":"<token>"},"meta":null}
     * @response 422 {"success":false,"status":422,"message":"Validation failed.","errors":{"code":["Invalid verification code."]},"meta":null}
     */
    public function verify(VerifyMfaChallengeRequest $request)
    {
        $data = $request->validated();

        return response()->success('MFA verification succeeded.', $this->challenges->verifyLoginChallenge(
            'consumer',
            (string) $data['challenge_token'],
            (string) $data['code']
        ));
    }

    /**
     * Resend login MFA challenge (consumer)
     *
     * Resends a code for resend-capable drivers (for example `email_otp`).
     *
     * @bodyParam challenge_token string required Opaque MFA challenge token returned from the login `202` response. Example: 5d2c9e8f4b6a1d3c7e9f0a2b4c6d8e0f5a1b3c7d9e2f4a6c8b0d1e3f5a7c9e1
     *
     * @response 200 {"success":true,"status":200,"message":"MFA code resent.","data":{"challenge_token":"<token>","driver":"email_otp","context":"consumer","purpose":"login","expires_at":"2026-02-24T20:30:00Z","can_resend":true,"masked_destination":"j***e@example.com","resend_count":1,"max_resends":3,"sent_at":"2026-02-24T20:25:05Z","delivery":{"channel":"email"}},"meta":null}
     * @response 422 {"success":false,"status":422,"message":"Validation failed.","errors":{"challenge_token":["Please wait before requesting another code."]},"meta":null}
     */
    public function resend(ResendMfaChallengeRequest $request)
    {
        $data = $request->validated();

        return response()->success('MFA code resent.', $this->challenges->resendLoginChallenge(
            'consumer',
            (string) $data['challenge_token']
        ));
    }
}
