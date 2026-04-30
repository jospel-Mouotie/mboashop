<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    protected $table = 'drivers';

    protected $fillable = [
        'user_id', 'vehicle_type', 'license_plate', 'id_card',
        'status', 'is_online', 'rating', 'total_deliveries',
        'total_earnings', 'current_balance'
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'rating' => 'decimal:1',
        'total_earnings' => 'integer',
        'current_balance' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(DriverLocation::class, 'driver_id', 'user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverAssignment::class, 'driver_id', 'user_id');
    }

    public function getLastLocationAttribute()
    {
        return $this->locations()->latest('recorded_at')->first();
    }
}
