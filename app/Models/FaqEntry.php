<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqEntry extends Model
{
    protected $fillable = [
        'intent_code',
        'category',
        'keywords',
        'answer_text',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'json',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }
}
