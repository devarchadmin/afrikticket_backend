<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundraisingImage extends Model
{
    protected $fillable = [
        'fundraising_id',
        'image_path',
        'is_main'
    ];

    public function fundraising()
    {
        return $this->belongsTo(Fundraising::class);
    }
}
