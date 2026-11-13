<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Messages for the AI Business Chat assistant. */
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_chat_conversations')->onDelete('cascade');
            $table->enum('role', ['system', 'user', 'assistant', 'tool']);
            $table->longText('content');
            $table->json('tool_calls')->nullable()
                ->comment('Structured tool calls the assistant proposed for this message.');
            $table->json('tool_results')->nullable()
                ->comment('Results returned by the executed tools.');
            $table->json('grounding')->nullable()
                ->comment('Data sources and periods the answer is based on.');
            $table->json('metrics')->nullable()
                ->comment('Key figures quoted in the answer for auditability.');
            $table->integer('tokens_used')->default(0);
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
