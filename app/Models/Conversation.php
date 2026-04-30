<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'order_id', 'client_id', 'driver_id', 'status', 'closed_at'
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
