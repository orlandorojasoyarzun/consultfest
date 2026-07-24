<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'channels',
        'anticipation_days',
        'frequency',
        'genre_interests',
        'country_interests',
        'notify_opening',
        'notify_deadline',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'anticipation_days' => 'array',
            'genre_interests' => 'array',
            'country_interests' => 'array',
            'notify_opening' => 'boolean',
            'notify_deadline' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
