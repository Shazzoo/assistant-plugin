<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Geschoonde transcripties: geen naam, e-mail of IP-adres; het sessienummer is per gesprek willekeurig.
        Schema::create('assistant_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('session_number')->unique();
            $table->string('page')->nullable();
            $table->string('language', 8)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('assistant_conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->string('source')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        // Eigen tabel met een eigen levensloop: blijft staan als de transcriptie al is verwijderd.
        Schema::create('assistant_unanswered_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('normalized_key')->unique();
            $table->text('question');
            $table->unsignedInteger('times_asked')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->string('page')->nullable();
            $table->string('reason');
            $table->string('status')->default('nieuw');
            $table->string('assignee')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'times_asked']);
        });

        // Samengevatte cijfers per dag: geen persoonsgegevens, dus onbeperkt te bewaren.
        Schema::create('assistant_daily_statistics', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('conversations')->default(0);
            $table->unsignedInteger('questions')->default(0);
            $table->unsignedInteger('answered')->default(0);
            $table->unsignedInteger('no_source')->default(0);
            $table->unsignedInteger('unclear_source')->default(0);
            $table->unsignedInteger('out_of_bounds')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('shared')->default(0);
            $table->json('sources')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_daily_statistics');
        Schema::dropIfExists('assistant_unanswered_questions');
        Schema::dropIfExists('assistant_conversation_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
