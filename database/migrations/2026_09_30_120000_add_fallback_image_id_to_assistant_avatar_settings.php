<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_avatar_settings', function (Blueprint $table): void {
            $table->foreignId('fallback_image_id')
                ->nullable()
                ->after('avatar_id')
                ->constrained('media')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assistant_avatar_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fallback_image_id');
        });
    }
};
