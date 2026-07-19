<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
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

    /**
     * Determine if the order can still be cancelled by the retailer
     */
    public function getCanCancelAttribute(): bool
    {
        return $this->status === 'pending' 
            && $this->cancellation_deadline 
            && now()->lessThan($this->cancellation_deadline);
    }

    /**
     * Retailer who created the order
     */
    public function retailer()
    {
        return $this->belongsTo(Retailer::class, 'retailer_id');
    }

    /**
     * Assigned driver
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Admin who confirmed order
     */
    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Order items
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Delivery
     */
    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    /**
     * Payment
     */
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}