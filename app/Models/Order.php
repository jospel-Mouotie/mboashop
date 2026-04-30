<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'shop_id', 'driver_id',
        'subtotal', 'shipping_cost', 'total_amount',
        'delivery_address', 'delivery_phone', 'status', 'notes'
    ];

    // Relation avec le client
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relation avec la boutique
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // Relation avec le livreur
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    // Relation avec les articles de la commande
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Relation avec la livraison (validation croisée)
    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    // Relation avec l'historique des statuts
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    // Générer un numéro de commande unique
    public static function generateOrderNumber()
    {
        $prefix = 'MBOA';
        $date = now()->format('Ymd');
        $random = str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $number = $prefix . '-' . $date . '-' . $random;

        while (self::where('order_number', $number)->exists()) {
            $random = str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $number = $prefix . '-' . $date . '-' . $random;
        }

        return $number;
    }

    // Ajouter un historique de statut
    public function addStatusHistory($status, $comment = null, $userId = null)
    {
        return OrderStatusHistory::create([
            'order_id' => $this->id,
            'status' => $status,
            'comment' => $comment,
            'user_id' => $userId,
        ]);
    }
}
