<?php

namespace NinjaPortal\Mfa\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use NinjaPortal\Mfa\Http\Requests\Concerns\MfaChallengeRules;

class ResendMfaChallengeRequest extends FormRequest
{
    use MfaChallengeRules;

    public function authorize(): bool { return true; }

    public function rules(): array { return $this->resendRules(); }
}
