<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Festival extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_id',
        'name',
        'category',
        'country',
        'deadline',
        'opening_date',
        'submission_fee',
        'accepting_submissions',
        'festival_score',
        'details',
        'last_synced_at',
    ];

    /**
     * Hide the raw API payload from serialization. It can contain
     * descriptions, URLs and other fields that should not leak to clients.
     */
    protected $hidden = [
        'details',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'opening_date' => 'date',
            'details' => 'array',
            'accepting_submissions' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeDeadlineBetween($query, $start, $end)
    {
        return $query->whereBetween('deadline', [$start, $end]);
    }

    public function scopeOpeningBetween($query, $start, $end)
    {
        return $query->whereBetween('opening_date', [$start, $end]);
    }
}
