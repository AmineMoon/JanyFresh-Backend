<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Post;
use App\Models\Retailer;
use App\Models\Order;
use App\Models\JaniEmployee;
use App\Models\Driver;


class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'name',
        'image',
        'email',
        'password',
        'phone',
        'role',
        'is_active',
    ];

    /**
     * Hidden attributes
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /* =========================
       ROLE HELPERS (IMPORTANT)
    ========================== */

    public function isJani(): bool
    {
        return $this->role === 'employee';
    }

    public function isDriver(): bool
    {
        return $this->role === 'driver';
    }

    public function isRetailer(): bool
    {
        return $this->role === 'retailer';
    }

    /* =========================
       RELATIONSHIPS
    ========================== */

    public function retailer()
    {
        return $this->hasOne(Retailer::class);
    }

    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    public function janiEmployee()
    {
        return $this->hasOne(JaniEmployee::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function retailerOrders()
    {
        return $this->hasManyThrough(Order::class, Retailer::class);
    }

      public function isAdmin(): bool
{
    return $this->role === 'admin';
}

}

    /**
     * Optionally add any additional logic or mutators here for email and password handling
     */
    // Add any custom setters or getters if needed for email or password processing.
