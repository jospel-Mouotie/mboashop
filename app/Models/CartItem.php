<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'user_id', 'product_id', 'quantity', 'price_at_add', 'options'
    ];

    protected $casts = [
        'options' => 'array',
        'price_at_add' => 'integer',
    ];

    // Relation avec l'utilisateur
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relation avec le produit
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Calculer le sous-total pour cet article
    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->price_at_add;
    }
}
