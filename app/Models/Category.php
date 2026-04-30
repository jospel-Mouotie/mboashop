<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'image', 'parent_id'
    ];

    // Relation parent (catégorie parente)
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Relation enfants (sous-catégories)
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Relation produits
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
    public function interestedUsers()
{
    return $this->belongsToMany(User::class, 'user_interests', 'category_id', 'user_id')
                ->withTimestamps();
}
}
