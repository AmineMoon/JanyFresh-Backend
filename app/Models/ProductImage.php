<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\product;

class ProductImage extends Model
{
     protected $fillable = [
        'product_id',
        'image_path',
        'position',
        'is_primary',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_primary' => 'boolean',
    ];

    // Include both 'url' (for frontend compatibility) and 'image_url' (for consistency)
    protected $appends = ['image_url', 'url'];

    // ✅ Accessor: returns full absolute URL for the stored image
    // Frontend uses 'url' field
    public function getUrlAttribute()
    {
        if (empty($this->image_path)) {
            return null;
        }

        // If the path is already a full URL, return as-is
        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return $this->image_path;
        }

        return asset('storage/' . ltrim($this->image_path, '/'));
    }

    // Alias for consistency
    public function getImageUrlAttribute()
    {
        return $this->url;
    }

    // ✅ Relationship

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}