<?php

namespace NinjaPortal\Mfa\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class BeginAuthenticatorEnrollmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
