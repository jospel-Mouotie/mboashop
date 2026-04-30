<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $fillable = [
        'order_id', 'pin', 'pin_expires_at', 'pin_attempts',
        'client_validated', 'client_validated_at',
        'driver_validated', 'driver_validated_at',
        'delivery_latitude', 'delivery_longitude', 'proof_photo',
        'reminder_sent', 'last_reminder_sent_at'
    ];

    protected $casts = [
        'pin_expires_at' => 'datetime',
        'client_validated_at' => 'datetime',
        'driver_validated_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'client_validated' => 'boolean',
        'driver_validated' => 'boolean',
        'reminder_sent' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Générer un PIN aléatoire à 6 chiffres
    public static function generatePin()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // Vérifier si la validation est complète (les deux ont validé)
    public function isFullyValidated(): bool
    {
        return $this->client_validated && $this->driver_validated;
    }

    // Vérifier si le PIN est expiré
    public function isPinExpired(): bool
    {
        return now()->gt($this->pin_expires_at);
    }
}
