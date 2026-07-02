<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\favorites;
use App\Models\Order;

class Retailer extends Model
{
    use HasFactory;

    protected $table = 'retailers';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'user_id',
        'shop_name',
        'address',
        'city',
        'image',
        'age',
       
    ];

    /**
     * Casts
     */
    protected $casts = [
        'age' => 'integer',
         
    ];

    /* =========================
       RELATIONSHIPS
    ========================== */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function favorites()
   {
    return $this->hasMany(Favorite::class);
    }

   public function orders()
{
    return $this->hasMany(Order::class, 'retailer_id', 'user_id');
}
}