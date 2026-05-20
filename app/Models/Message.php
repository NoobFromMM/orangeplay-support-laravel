<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'customer_id', 'platform', 'direction', 'sender_type', 'message_type', 'text', 'raw_payload', 'metadata'])]
class Message extends Model
{
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supportCases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SupportCase::class);
    }

    protected function casts(): array
    {
        return [
            'raw_payload' => 'json',
            'metadata' => 'json',
        ];
    }
}
