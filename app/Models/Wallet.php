<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    // CORRECT FIELDS FOR WALLET
    protected $fillable = [
        'user_id', 
        'balance', 
        'cash_on_hand', 
        'earnings'
    ];
}