<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Wallet API
Route::get('/wallet/{id}', [WalletController::class, 'getWallet']);
Route::post('/recharge', [WalletController::class, 'rechargeRider']);
Route::post('/deduct', [WalletController::class, 'deductBalance']);
Route::post('/create_rider', [UserController::class, 'createRider']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/withdraw', [WalletController::class, 'withdrawEarnings']);
// In api.php

// Add this line inside your routes
// routes/api.php

Route::post('/find-rider', function (Request $request) {
    $query = $request->query_input; // We will send 'query_input' from Flutter

    $user = App\Models\User::where('role', 'rider')
        ->where(function($q) use ($query) {
            $q->where('mobile', $query)
              ->orWhere('name', 'like', "%{$query}%")
              ->orWhere('id', $query);
        })->first();

    if (!$user) {
        return response()->json(['message' => 'Rider not found'], 404);
    }
    return response()->json($user);
});

// 2. NEW ROUTE: Get Negative Wallets
Route::get('/negative-wallets', function () {
    // Find wallets with balance <= 0 and get their owner details
    $wallets = Wallet::where('balance', '<=', 0)
        ->with('user') // Ensure Wallet model has 'public function user() { return $this->belongsTo(User::class); }'
        ->get();
        
    return response()->json($wallets);
});