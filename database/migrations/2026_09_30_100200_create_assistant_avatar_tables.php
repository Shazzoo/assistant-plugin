<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alleen voor budget en gelijktijdigheid: geen gegevens van de bezoeker.
        Schema::create('assistant_avatar_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('session_id')->unique();
            $table->boolean('sandbox')->default(true);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();

            $table->index(['ended_at', 'started_at']);
        });

        // Eén rij met de instellingen van de pratende avatar, te wijzigen in het beheer.
        Schema::create('assistant_avatar_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            // Versleuteld met APP_KEY (encrypted cast); nooit leesbaar in de database of in het beheer.
            $table->text('api_key')->nullable();
            $table->boolean('sandbox')->default(true);
            $table->string('avatar_id')->nullable();
            $table->string('voice_id')->nullable();
            $table->string('context_id')->nullable();
            $table->string('language', 8)->default('nl');
            $table->string('quality')->default('medium');
            $table->unsignedInteger('idle_stop_seconds')->default(90);
            $table->unsignedInteger('max_session_seconds')->default(600);
            $table->unsignedInteger('max_concurrent')->default(3);
            $table->unsignedInteger('monthly_budget_minutes')->default(500);
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_avatar_settings');
        Schema::dropIfExists('assistant_avatar_sessions');
    }
};
