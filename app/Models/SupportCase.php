<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'conversation_id',
    'message_id',
    'platform',
    'platform_user_id',
    'category',
    'title',
    'description',
    'status',
    'priority',
    'source_text',
    'source_metadata',
    'admin_note',
    'resolved_at',
])]
class SupportCase extends Model
{
    public const CATEGORIES = [
        'app_error',
        'movie_request',
        'content_request',
        'account_help',
        'playback_issue',
        'subtitle_audio_issue',
        'billing_question',
        'complaint',
        'other',
    ];

    public const STATUSES = [
        'open',
        'in_progress',
        'resolved',
        'rejected',
    ];

    public const PRIORITIES = [
        'low',
        'normal',
        'high',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function displayCode(): string
    {
        return sprintf('#%03d', $this->id);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['open'], true);
    }

    protected function casts(): array
    {
        return [
            'source_metadata' => 'json',
            'resolved_at' => 'datetime',
        ];
    }

    public static function categoryOptions(): array
    {
        return self::CATEGORIES;
    }

    public static function statusOptions(): array
    {
        return self::STATUSES;
    }

    public static function priorityOptions(): array
    {
        return self::PRIORITIES;
    }

    public static function labelForCategory(string $category): string
    {
        return match ($category) {
            'app_error' => 'App Error',
            'movie_request' => 'Movie Request',
            'content_request' => 'Content Request',
            'account_help' => 'Account Help',
            'playback_issue' => 'Playback Issue',
            'subtitle_audio_issue' => 'Subtitle / Audio Issue',
            'billing_question' => 'Billing Question',
            'complaint' => 'Complaint',
            'other' => 'Other',
            default => $category,
        };
    }

    public static function labelForStatus(string $status): string
    {
        return match ($status) {
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'rejected' => 'Rejected',
            default => $status,
        };
    }

    public static function labelForPriority(string $priority): string
    {
        return match ($priority) {
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            default => $priority,
        };
    }
}
