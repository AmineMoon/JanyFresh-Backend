<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_AVAILABLE = 'available';
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'order_number',
        'retailer_id',
        'confirmed_by',
        'status',
        'cancellation_deadline',
        'subtotal',
        'discount',
        'delivery_fee',
        'total_price',
        'total',
    ];

    protected $casts = [
        'cancellation_deadline' => 'datetime',
    ];

    protected $appends = ['can_cancel'];

    public function getCanCancelAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->cancellation_deadline
            && now()->lessThan($this->cancellation_deadline);
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class, 'retailer_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_AVAILABLE,
            self::STATUS_ASSIGNED,
            self::STATUS_OUT_FOR_DELIVERY,
        ]);
    }

    public function scopeCancellable(Builder $query): Builder
    {
        return $query->pending()
            ->whereColumn('cancellation_deadline', '>', now());
    }
}
