<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Models\Wallet;
use App\Models\User;
use App\Models\Transaction;
use App\Http\Controllers\DailyDeliveryController;



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
Route::post('/refund-rider', [WalletController::class, 'refundRider']);
Route::post('/submit-daily-sheet', [DailyDeliveryController::class, 'submitDailySheet']);
Route::get('/rider-history/{id}', [DailyDeliveryController::class, 'getRiderHistory']);
Route::post('/get-daily-sheet', [DailyDeliveryController::class, 'getDailySheet']);
Route::get('/pending-sheets', [DailyDeliveryController::class, 'getPendingSheets']);
Route::post('/approve-sheet', [DailyDeliveryController::class, 'approveSheet']);
Route::get('/daily-status-report', [DailyDeliveryController::class, 'getDailyStatusReport']);
// In api.php

// Add this line inside your routes
// routes/api.php

// 1. UPDATED SEARCH: Include Wallet info
Route::post('/find-rider', function (Request $request) {
    $query = $request->query_input;

    // Use 'with('wallet')' to attach wallet data to the user
    $user = User::with('wallet')->where('role', 'rider')
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
    $wallets = Wallet::where('balance', '<=', 0)
        ->whereHas('user', function ($query) {
            // This filters the related 'users' table
            $query->where('role', 'rider');
        })  
        ->with('user') // This loads the user data so you can see names/mobiles
        ->get();
        
    return response()->json($wallets);
});

Route::post('/update-fcm', function (Request $request) {
    $user = User::find($request->user_id);
    if ($user) {
        $user->fcm_token = $request->token;
        $user->save();
        return response()->json(['message' => 'Token updated']);
    }
});

Route::get('/admin-history', function () {
    $history = Transaction::with(['wallet.user', 'admin']) // Load Rider (via wallet) and Admin info
        ->latest()
        ->limit(50)
        ->get();

    return response()->json($history);
});