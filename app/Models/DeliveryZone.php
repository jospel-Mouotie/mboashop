<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = [
        'name', 'city', 'polygon', 'base_price', 'price_per_km', 'is_active'
    ];

    protected $casts = [
        'polygon' => 'array',
        'is_active' => 'boolean',
    ];
}
