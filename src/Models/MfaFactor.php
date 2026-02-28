<?php

namespace NinjaPortal\Mfa\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MfaFactor extends Model
{
    use HasFactory;

    protected $table = 'portal_mfa_factors';

    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'driver',
        'label',
        'secret_encrypted',
        'is_enabled',
        'is_verified',
        'is_primary',
        'config',
        'verified_at',
        'last_used_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_verified' => 'boolean',
        'is_primary' => 'boolean',
        'config' => 'array',
        'verified_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
