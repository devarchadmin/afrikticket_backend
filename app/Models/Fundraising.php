<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Fundraising extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'goal',
        'current',
        'organization_id',
        'status',
        'category'
    ];

    protected $casts = [
        'goal' => 'decimal:2',
        'current' => 'decimal:2'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }
    public function images()
    {
        return $this->hasMany(FundraisingImage::class);
    }
    public function events()
    {
        return $this->hasMany(Event::class);
    }
}