<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = ['wallet_id', 'amount', 'type', 'description', 'admin_id', 'balance_after'];

    // Link to the Wallet (to get the Rider)
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    // Link to the Admin (who performed the action)
    public function admin()
    {
        // Assuming 'admin_id' points to the 'users' table
        return $this->belongsTo(User::class, 'admin_id');
    }
}