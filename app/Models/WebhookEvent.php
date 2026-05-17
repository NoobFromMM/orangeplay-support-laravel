<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = [
        'channel',
        'event_type',
        'external_event_id',
        'external_user_id',
        'payload',
        'status',
        'processed_at',
        'failed_at',
        'error_message',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
