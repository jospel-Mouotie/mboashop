<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $fillable = [
        'user_id', 'name', 'slug', 'description', 'logo',
        'address', 'city', 'phone', 'type', 'status', 'rating'
    ];

    // Relation avec l'utilisateur (commerçant ou grossiste)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relation avec les produits
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Générer un slug unique à partir du nom
    public static function generateSlug($name)
    {
        $slug = \Illuminate\Support\Str::slug($name);
        $count = static::where('slug', 'LIKE', "{$slug}%")->count();
        return $count ? "{$slug}-{$count}" : $slug;
    }
}
