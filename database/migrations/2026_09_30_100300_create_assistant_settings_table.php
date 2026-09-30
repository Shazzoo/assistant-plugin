<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eén rij met wat per site verschilt: wie de assistent is, wat hij weet en naar wie hij doorverwijst.
        Schema::create('assistant_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('company')->nullable();
            $table->text('greeting')->nullable();
            $table->longText('instructions')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('share_to')->nullable();
            $table->json('public_details')->nullable();
            $table->unsignedInteger('max_question_length')->default(500);
            $table->unsignedInteger('max_questions')->default(10);
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_settings');
    }
};
