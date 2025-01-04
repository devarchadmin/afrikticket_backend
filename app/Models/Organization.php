<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'description',
        'status',
        'icd_document',
        'commerce_register',
        'user_id'
    ];

    // Hide sensitive fields by default
    protected $hidden = [
        'icd_document',
        'status',
        'commerce_register'
    ];

    // Create a visible version with documents for organization owners
    protected $organizationOwnerVisible = [
        'id', 'name', 'email', 'phone', 'description', 
        'status', 'icd_document', 'commerce_register', 
        'user_id', 'created_at', 'updated_at'
    ];

    public function showSensitiveData()
    {
        $this->makeVisible(['icd_document', 'commerce_register']);
        return $this;
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function fundraisings()
    {
        return $this->hasMany(Fundraising::class);
    }
}
