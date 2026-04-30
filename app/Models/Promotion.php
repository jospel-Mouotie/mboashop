<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    protected $fillable = [
        'product_id', 'shop_id', 'discount_percentage',
        'start_date', 'end_date', 'is_flash_sale', 'status'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_flash_sale' => 'boolean',
    ];

    // Relation avec le produit
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Relation avec la boutique
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // Vérifier si la promotion est active
    public function isActive(): bool
    {
        $now = now();
        return $this->status === 'active'
            && $now >= $this->start_date
            && $now <= $this->end_date;
    }

    // Calculer le prix après réduction
    public function getDiscountedPrice($originalPrice)
    {
        return $originalPrice - ($originalPrice * $this->discount_percentage / 100);
    }
}
