<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    protected $table = 'ai_chat_messages';

    protected $fillable = [
        'conversation_id', 'role', 'content',
        'tool_calls', 'tool_results', 'grounding', 'metrics', 'tokens_used',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'tool_results' => 'array',
        'grounding' => 'array',
        'metrics' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'conversation_id');
    }
}
