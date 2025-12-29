<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyDelivery extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Automatically convert the JSON string to an Array when reading
    protected $casts = [
        'records_json' => 'array',
        'delivery_date' => 'date',
    ];

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }
}