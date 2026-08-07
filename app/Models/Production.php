<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Production extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscriber_id',
        'title',
        'synopsis',
        'runtime_minutes',
        'format',
        'country',
        'production_year',
        'category',
        'genres',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'genres' => 'array',
            'runtime_minutes' => 'integer',
            'production_year' => 'integer',
        ];
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }
}