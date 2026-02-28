<?php

namespace NinjaPortal\Mfa\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class MfaChallenge extends Model
{
    use HasFactory;

    protected $table = 'portal_mfa_challenges';

    protected $fillable = [
        'token_hash',
        'context',
        'purpose',
        'authenticatable_type',
        'authenticatable_id',
        'mfa_factor_id',
        'driver',
        'code_hash',
        'attempts',
        'max_attempts',
        'resend_count',
        'max_resends',
        'last_sent_at',
        'expires_at',
        'completed_at',
        'invalidated_at',
        'payload',
        'meta',
    ];

    protected $casts = [
        'payload' => 'array',
        'meta' => 'array',
        'last_sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'invalidated_at' => 'datetime',
    ];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function factor(): BelongsTo
    {
        return $this->belongsTo(MfaFactor::class, 'mfa_factor_id');
    }

    public function isPending(): bool
    {
        return $this->completed_at === null && $this->invalidated_at === null && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof Carbon && $this->expires_at->isPast();
    }
}
