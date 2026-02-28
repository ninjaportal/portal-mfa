<?php

namespace NinjaPortal\Mfa\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmEmailOtpEnrollmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'min:32', 'max:255'],
            'code' => ['required', 'string', 'min:4', 'max:16'],
        ];
    }
}
