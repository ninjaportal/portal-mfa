<?php

namespace NinjaPortal\Mfa\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MfaProfile extends Model
{
    use HasFactory;

    protected $table = 'portal_mfa_profiles';

    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'is_enabled',
        'preferred_driver',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
