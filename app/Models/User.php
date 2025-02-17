<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'profile_image',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function organization()
    {
        return $this->hasOne(Organization::class);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isOrganization()
    {
        return $this->role === 'organization';
    }

    // Add this relationship method to the User model
    public function admin()
    {
        return $this->hasOne(Admin::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }
}
