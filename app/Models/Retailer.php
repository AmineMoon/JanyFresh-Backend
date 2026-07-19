<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * The user account this retailer belongs to
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Orders placed by this retailer
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'retailer_id');
    }

    /**
     * Favorites saved by this retailer
     */
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }
}