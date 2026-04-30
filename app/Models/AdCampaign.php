<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCampaign extends Model
{
    protected $table = 'ad_campaigns';

    protected $fillable = [
        'shop_id', 'title', 'type', 'image_url', 'target_url',
        'product_id', 'start_date', 'end_date', 'amount_paid', 'status',
        'impressions', 'clicks'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isActive(): bool
    {
        $now = now();
        return $this->status === 'active'
            && $now >= $this->start_date
            && $now <= $this->end_date;
    }
}
