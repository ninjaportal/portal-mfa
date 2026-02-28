<?php

use Illuminate\Support\Facades\Route;
use NinjaPortal\Mfa\Http\Controllers\V1\Admin\MfaChallengeController as AdminMfaChallengeController;
use NinjaPortal\Mfa\Http\Controllers\V1\Admin\MfaSettingsController as AdminMfaSettingsController;
use NinjaPortal\Mfa\Http\Controllers\V1\User\MfaChallengeController as UserMfaChallengeController;
use NinjaPortal\Mfa\Http\Controllers\V1\User\MfaSettingsController as UserMfaSettingsController;

if (! config('portal-mfa.routes.enabled', true)) {
    return;
}

Route::prefix(config('portal-api.prefix', 'api/v1'))
    ->middleware(config('portal-api.middleware', ['api']))
    ->group(function () {
        $adminPrefix = (string) config('portal-api.admin_prefix', 'admin');
        $consumerGuard = (string) config('portal-api.auth.guards.consumer', 'api');
        $adminGuard = (string) config('portal-api.auth.guards.admin', 'portal_api_admin');

        // Public challenge endpoints (complete login after password step).
        Route::post('/auth/mfa/challenge/verify', [UserMfaChallengeController::class, 'verify'])
            ->name('portal-api.auth.mfa.challenge.verify');
        Route::post('/auth/mfa/challenge/resend', [UserMfaChallengeController::class, 'resend'])
            ->name('portal-api.auth.mfa.challenge.resend');

        Route::prefix($adminPrefix)->group(function () {
            Route::post('/auth/mfa/challenge/verify', [AdminMfaChallengeController::class, 'verify'])
                ->name('portal-api.admin.auth.mfa.challenge.verify');
            Route::post('/auth/mfa/challenge/resend', [AdminMfaChallengeController::class, 'resend'])
                ->name('portal-api.admin.auth.mfa.challenge.resend');
        });

        // Authenticated settings/factor management endpoints.
        Route::middleware("auth:{$consumerGuard}")->group(function () {
            Route::get('/me/mfa', [UserMfaSettingsController::class, 'show'])->name('portal-api.me.mfa.show');
            Route::put('/me/mfa', [UserMfaSettingsController::class, 'update'])->name('portal-api.me.mfa.update');
            Route::post('/me/mfa/authenticator/setup', [UserMfaSettingsController::class, 'beginAuthenticator'])->name('portal-api.me.mfa.authenticator.setup');
            Route::post('/me/mfa/authenticator/confirm', [UserMfaSettingsController::class, 'confirmAuthenticator'])->name('portal-api.me.mfa.authenticator.confirm');
            Route::delete('/me/mfa/authenticator', [UserMfaSettingsController::class, 'disableAuthenticator'])->name('portal-api.me.mfa.authenticator.disable');
            Route::post('/me/mfa/email-otp/start', [UserMfaSettingsController::class, 'beginEmailOtp'])->name('portal-api.me.mfa.email-otp.start');
            Route::post('/me/mfa/email-otp/confirm', [UserMfaSettingsController::class, 'confirmEmailOtp'])->name('portal-api.me.mfa.email-otp.confirm');
            Route::delete('/me/mfa/email-otp', [UserMfaSettingsController::class, 'disableEmailOtp'])->name('portal-api.me.mfa.email-otp.disable');
        });

        Route::prefix($adminPrefix)->middleware("auth:{$adminGuard}")->group(function () {
            Route::get('/me/mfa', [AdminMfaSettingsController::class, 'show'])->name('portal-api.admin.me.mfa.show');
            Route::put('/me/mfa', [AdminMfaSettingsController::class, 'update'])->name('portal-api.admin.me.mfa.update');
            Route::post('/me/mfa/authenticator/setup', [AdminMfaSettingsController::class, 'beginAuthenticator'])->name('portal-api.admin.me.mfa.authenticator.setup');
            Route::post('/me/mfa/authenticator/confirm', [AdminMfaSettingsController::class, 'confirmAuthenticator'])->name('portal-api.admin.me.mfa.authenticator.confirm');
            Route::delete('/me/mfa/authenticator', [AdminMfaSettingsController::class, 'disableAuthenticator'])->name('portal-api.admin.me.mfa.authenticator.disable');
            Route::post('/me/mfa/email-otp/start', [AdminMfaSettingsController::class, 'beginEmailOtp'])->name('portal-api.admin.me.mfa.email-otp.start');
            Route::post('/me/mfa/email-otp/confirm', [AdminMfaSettingsController::class, 'confirmEmailOtp'])->name('portal-api.admin.me.mfa.email-otp.confirm');
            Route::delete('/me/mfa/email-otp', [AdminMfaSettingsController::class, 'disableEmailOtp'])->name('portal-api.admin.me.mfa.email-otp.disable');
        });
    });
