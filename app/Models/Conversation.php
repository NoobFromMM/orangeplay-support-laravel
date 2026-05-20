<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'status', 'bot_paused', 'last_message_at'])]
class Conversation extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function supportCases(): HasMany
    {
        return $this->hasMany(SupportCase::class);
    }

    protected function casts(): array
    {
        return [
            'bot_paused' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    public function updateWorkflow(string $status, ?bool $botPaused = null): void
    {
        $this->status = $status;

        if ($botPaused !== null) {
            $this->bot_paused = $botPaused;
        }

        $this->save();
    }

    public function isBotPaused(): bool
    {
        return (bool) $this->bot_paused;
    }
}
