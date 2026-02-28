<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_mfa_factors', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable', 'pmfa_factors_auth_idx');
            $table->string('driver', 64);
            $table->string('label', 120)->nullable();
            $table->longText('secret_encrypted')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->json('config')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['authenticatable_type', 'authenticatable_id', 'driver'], 'portal_mfa_factors_actor_driver_unique');
            $table->index(['driver', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_mfa_factors');
    }
};
