<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Het id komt uit het kennisbestand, zodat een antwoord herleidbaar blijft.
        Schema::create('assistant_knowledge_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('question');
            $table->text('variants')->nullable();
            $table->text('answer')->nullable();
            $table->string('category')->nullable();
            $table->string('status');
            $table->string('source')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('owner')->nullable();
            $table->date('checked_at')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        // Alleen wie expliciet akkoord gaf, mag genoemd worden.
        Schema::create('assistant_employees', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('role');
            $table->string('expertise')->nullable();
            $table->string('hobby')->nullable();
            $table->unsignedTinyInteger('years_of_experience')->nullable();
            $table->boolean('may_be_named')->default(false);
            $table->date('consented_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('assistant_client_references', function (Blueprint $table): void {
            $table->id();
            $table->string('client');
            $table->string('sector');
            $table->string('size')->nullable();
            $table->text('what_we_did')->nullable();
            $table->boolean('name_released')->default(false);
            $table->boolean('figures_released')->default(false);
            $table->date('released_at')->nullable();
            $table->string('recorded_in')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_client_references');
        Schema::dropIfExists('assistant_employees');
        Schema::dropIfExists('assistant_knowledge_entries');
    }
};
