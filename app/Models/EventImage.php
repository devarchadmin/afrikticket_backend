<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventImage extends Model
{
    protected $fillable = [
        'event_id',
        'image_path',
        'is_main'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
