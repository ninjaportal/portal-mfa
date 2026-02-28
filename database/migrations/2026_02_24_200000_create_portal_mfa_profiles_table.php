<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_mfa_profiles', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable', 'pmfa_profiles_auth_idx');
            $table->boolean('is_enabled')->default(false);
            $table->string('preferred_driver', 64)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['authenticatable_type', 'authenticatable_id'], 'portal_mfa_profiles_actor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_mfa_profiles');
    }
};
