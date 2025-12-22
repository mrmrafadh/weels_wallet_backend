<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\User;

class WalletController extends Controller
{
    // 1. GET WALLET (Auto-creates if missing)
    public function getWallet($userId)
    {
        $wallet = Wallet::where('user_id', $userId)->first();

        if (!$wallet) {
            // Ensure User Exists first
            $user = User::find($userId);
            if (!$user) {
                // Auto-create user for testing purposes
                $user = User::create([
                    'id' => $userId, 
                    'name' => 'User '.$userId, 
                    'role' => 'rider'
                ]);
            }

            // Create Wallet
            $wallet = Wallet::create([
                'user_id' => $userId,
                'balance' => 0,
                'cash_on_hand' => 0,
                'earnings' => 0
            ]);
        }

        $history = Transaction::where('wallet_id', $wallet->id)->latest()->get();

        return response()->json(['wallet' => $wallet, 'history' => $history]);
    }

    // 2. RECHARGE (Admin gets Cash -> Rider gets Balance)
    public function rechargeRider(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $riderWallet = Wallet::where('user_id', $request->rider_id)->first();
            $adminWallet = Wallet::where('user_id', $request->admin_id)->first();

            // Validation: Ensure Admin Wallet exists for tracking cash
            if (!$adminWallet) $adminWallet = $this->createEmptyWallet($request->admin_id);
            if (!$riderWallet) $riderWallet = $this->createEmptyWallet($request->rider_id);

            // Logic
            $riderWallet->increment('balance', $request->amount);
            $adminWallet->increment('cash_on_hand', $request->amount);

            Transaction::create([
                'wallet_id' => $riderWallet->id,
                'admin_id' => $request->admin_id,
                'amount' => $request->amount,
                'type' => 'recharge',
                'description' => 'Cash Recharge'
            ]);

            return response()->json(['message' => 'Recharge Successful']);
        });
    }

    // 3. DEDUCT (Rider loses Balance -> Company Earns)
    public function deductBalance(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $riderWallet = Wallet::where('user_id', $request->rider_id)->first();
            $adminWallet = Wallet::where('user_id', $request->admin_id)->first();

            if (!$riderWallet || $riderWallet->balance < $request->amount) {
                return response()->json(['error' => 'Insufficient balance'], 400);
            }
            if (!$adminWallet) $adminWallet = $this->createEmptyWallet($request->admin_id);

            $riderWallet->decrement('balance', $request->amount);
            $adminWallet->increment('earnings', $request->amount);

            Transaction::create([
                'wallet_id' => $riderWallet->id,
                'admin_id' => $request->admin_id,
                'amount' => -$request->amount,
                'type' => 'deduction',
                'description' => $request->reason ?? 'Admin Deduction'
            ]);

            return response()->json(['message' => 'Deducted Successfully']);
        });
    }

    // Inside WalletController class

public function withdrawEarnings(Request $request)
{
    return DB::transaction(function () use ($request) {
        $adminWallet = Wallet::where('user_id', $request->admin_id)->first();

        // 1. Validation
        if (!$adminWallet) {
            return response()->json(['error' => 'Wallet not found'], 404);
        }
        if ($adminWallet->earnings < $request->amount) {
            return response()->json(['error' => 'You do not have enough earnings'], 400);
        }
        if ($adminWallet->cash_on_hand < $request->amount) {
            return response()->json(['error' => 'Not enough physical cash in the box'], 400);
        }

        // 2. Perform Withdrawal
        // Decrease Earnings (because you are taking them)
        $adminWallet->decrement('earnings', $request->amount);
        
        // Decrease Cash (because you are taking money out of the box)
        $adminWallet->decrement('cash_on_hand', $request->amount);

        // 3. Record Transaction
        Transaction::create([
            'wallet_id' => $adminWallet->id, // Admin's own wallet ID
            'admin_id' => $request->admin_id,
            'amount' => -$request->amount,
            'type' => 'withdraw',
            'description' => 'Profit Withdrawal'
        ]);

        return response()->json(['message' => 'Withdrawal Successful', 'wallet' => $adminWallet]);
    });
}

    // Helper function
    private function createEmptyWallet($userId) {
         return Wallet::create(['user_id' => $userId]);
    }

    // Add refund and withdraw functions similarly if needed...
}