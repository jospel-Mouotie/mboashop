<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'shop_id', 'category_id', 'name', 'slug', 'description',
        'price', 'stock', 'unit', 'status', 'views'
    ];

    // Relation avec la boutique
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // Relation avec la catégorie
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Relation avec les photos (images multiples)
    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhoto::class);
    }

    // Récupérer la photo principale
    public function primaryPhoto()
    {
        return $this->hasOne(ProductPhoto::class)->where('is_primary', true);
    }

    // Générer un slug unique
    public static function generateSlug($name, $shopId)
    {
        $slug = \Illuminate\Support\Str::slug($name);
        $count = static::where('slug', 'LIKE', "{$slug}%")->where('shop_id', $shopId)->count();
        return $count ? "{$slug}-{$count}" : $slug;
    }

    // Vérifier si le produit est en stock
    public function inStock(): bool
    {
        return $this->stock > 0 && $this->status === 'active';
    }

    // Relation avec les promotions
public function promotions()
{
    return $this->hasMany(Promotion::class);
}

// Récupérer la promotion active
public function activePromotion()
{
    return $this->hasOne(Promotion::class)
        ->where('status', 'active')
        ->where('start_date', '<=', now())
        ->where('end_date', '>=', now());
}
}
