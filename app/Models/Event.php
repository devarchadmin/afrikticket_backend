<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'date',
        'location',
        'max_tickets',
        'price',
        'organization_id',
        // 'fundraising_id',
        'status'
    ];

    protected $casts = [
        'date' => 'datetime',
        'price' => 'decimal:2'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function fundraising()
    {
        return $this->belongsTo(Fundraising::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
