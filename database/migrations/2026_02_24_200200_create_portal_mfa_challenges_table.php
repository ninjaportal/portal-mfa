<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_mfa_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('context', 32);
            $table->string('purpose', 64); // login|factor_email_enrollment
            $table->morphs('authenticatable', 'pmfa_challenges_auth_idx');
            $table->foreignId('mfa_factor_id')->nullable()->constrained('portal_mfa_factors')->nullOnDelete();
            $table->string('driver', 64);
            $table->string('code_hash', 64)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->unsignedSmallInteger('resend_count')->default(0);
            $table->unsignedSmallInteger('max_resends')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->json('payload')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['context', 'purpose', 'driver']);
            $table->index(['expires_at', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_mfa_challenges');
    }
};
