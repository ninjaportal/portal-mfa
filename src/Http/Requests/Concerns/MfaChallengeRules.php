<?php

namespace NinjaPortal\Mfa\Http\Requests\Concerns;

trait MfaChallengeRules
{
    protected function verifyRules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'min:32', 'max:255'],
            'code' => ['required', 'string', 'min:4', 'max:16'],
        ];
    }

    protected function resendRules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'min:32', 'max:255'],
        ];
    }

    protected function settingsRules(): array
    {
        return [
            'is_enabled' => ['sometimes', 'boolean'],
            'preferred_driver' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
