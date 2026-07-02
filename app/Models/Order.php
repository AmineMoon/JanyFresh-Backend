<?php

namespace App\Models;
use App\Models\Product;
use App\Models\Order;
use App\Models\Retailer;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
       'order_number',
        'retailer_id',
        'confirmed_by',
        'status',
        'subtotal',
        'discount',
        'delivery_fee',
        'total_price',
        'total',
    ];

    /**
     * Retailer who created the order
     */
    public function retailer()
    {
        return $this->belongsTo(User::class, 'retailer_id');
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

     
   public function payment()
{
    return $this->hasOne(Payment::class);
}


}