<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    // CORRECT FIELDS FOR TRANSACTIONS
    protected $fillable = [
        'wallet_id', 
        'admin_id', 
        'amount', 
        'type', 
        'description'
    ];
}