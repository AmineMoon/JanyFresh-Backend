<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExclusiveOffer extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'discount_percentage',
        'badge_text',
        'button_text',
        'image',
        'status',
        'sort_order',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'discount_percentage' => 'integer',
        'sort_order' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? url('storage/' . $this->image) : null;
    }
}
