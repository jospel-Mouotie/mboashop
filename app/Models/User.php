<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * Les attributs qui sont assignables en masse.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',        // client, commerçant, livreur, admin
        'avatar',
        'email_verified_at',
    ];

    /**
     * Les attributs cachés pour les tableaux.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Les attributs castés.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Vérifier si l'utilisateur est admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Vérifier si l'utilisateur est commerçant
     */
    public function isSeller(): bool
    {
        return $this->role === 'commercant';
    }

    /**
     * Vérifier si l'utilisateur est livreur
     */


    public function isDriver(): bool
    {
        return $this->role === 'livreur';
    }

    /**
     * Relation avec la boutique (pour les commerçants)
     */
   public function shop()
{
    return $this->hasOne(Shop::class);
}

    // Relation avec les centres d'intérêt
public function interests()
{
    return $this->belongsToMany(Category::class, 'user_interests', 'user_id', 'category_id')
                ->withTimestamps();
}
}
