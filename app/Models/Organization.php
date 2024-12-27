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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}