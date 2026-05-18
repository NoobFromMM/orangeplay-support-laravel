<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCase extends Model
{
    protected $fillable = [
        'customer_id',
        'conversation_id',
        'image_message_id',
        'provider',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'customer_email',
        'worker_response',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'worker_response' => 'array',
            'reviewed_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function imageMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'image_message_id');
    }
}
