<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService; // Import Notification Service if you use it

class WalletController extends Controller
{
    // 1. GET WALLET (Auto-creates if missing)
    public function getWallet($userId)
    {
        $wallet = Wallet::where('user_id', $userId)->first();

        if (!$wallet) {
            $user = User::find($userId);
            if (!$user) {
                // Auto-create user for testing purposes
                $user = User::create([
                    'id' => $userId, 
                    'name' => 'User '.$userId, 
                    'role' => 'rider'
                ]);
            }

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

            // Create wallets if they don't exist
            if (!$adminWallet) $adminWallet = $this->createEmptyWallet($request->admin_id);
            if (!$riderWallet) $riderWallet = $this->createEmptyWallet($request->rider_id);

            // Logic
            $riderWallet->increment('balance', $request->amount);
            $adminWallet->increment('cash_on_hand', $request->amount);

            // Record Transaction with SNAPSHOT
            Transaction::create([
                'wallet_id' => $riderWallet->id,
                'admin_id' => $request->admin_id,
                'amount' => $request->amount,
                'balance_after' => $riderWallet->balance, // <--- Correctly saves new balance
                'type' => 'recharge',
                'description' => $request->reason ?? 'Cash Recharge'
            ]);

            // Optional: Send Notification
            $this->sendNotification($request->rider_id, 'Wallet Recharged 💰', "Your wallet has been recharged by \${$request->amount}. New Balance: \${$riderWallet->balance}");

            return response()->json(['message' => 'Recharge Successful']);
        });
    }

    // 3. DEDUCT (Rider loses Balance -> Company Earns)
    public function deductBalance(Request $request)
    {   
        // ERROR FIXED: Removed the code that was here "outside" the transaction

        return DB::transaction(function () use ($request) {
            $riderWallet = Wallet::where('user_id', $request->rider_id)->first();
            $adminWallet = Wallet::where('user_id', $request->admin_id)->first();

            if (!$riderWallet) {
                return response()->json(['error' => 'Rider wallet not found'], 404);
            }

            // Check if balance is sufficient
            if ($riderWallet->balance < $request->amount) {
                // If "force" is NOT true, stop and ask for confirmation
                if (!$request->force) {
                    return response()->json([
                        'error' => 'CONFIRM_LOW_BALANCE',
                        'message' => "Insufficient balance (Current: \${$riderWallet->balance}). Continue anyway?"
                    ], 409); 
                }
            }

            if (!$adminWallet) $adminWallet = $this->createEmptyWallet($request->admin_id);

            // Perform Update
            $riderWallet->decrement('balance', $request->amount);
            $adminWallet->increment('earnings', $request->amount);

            // Record Transaction with SNAPSHOT
            Transaction::create([
                'wallet_id' => $riderWallet->id,
                'admin_id' => $request->admin_id,
                'amount' => -$request->amount,
                'balance_after' => $riderWallet->balance, // <--- Correctly saves new balance
                'type' => 'deduction',
                'description' => $request->reason ?? 'Admin Deduction'
            ]);

            // Optional: Send Notification
            $this->sendNotification($request->rider_id, 'Balance Deducted ⚠️', "Your wallet has been deducted by \${$request->amount}. New Balance: \${$riderWallet->balance}");

            return response()->json(['message' => 'Deducted Successfully']);
        });
    }

    // 4. WITHDRAW (Admin takes profit)
    public function withdrawEarnings(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $adminWallet = Wallet::where('user_id', $request->admin_id)->first();

            if (!$adminWallet) {
                return response()->json(['error' => 'Wallet not found'], 404);
            }
            if ($adminWallet->earnings < $request->amount) {
                return response()->json(['error' => 'You do not have enough earnings'], 400);
            }
            if ($adminWallet->cash_on_hand < $request->amount) {
                return response()->json(['error' => 'Not enough physical cash in the box'], 400);
            }

            $adminWallet->decrement('earnings', $request->amount);
            $adminWallet->decrement('cash_on_hand', $request->amount);

            // Record Transaction (No balance_after needed for admin withdrawal usually, or use earnings)
            Transaction::create([
                'wallet_id' => $adminWallet->id,
                'admin_id' => $request->admin_id,
                'amount' => -$request->amount,
                'type' => 'withdraw',
                'description' => 'Profit Withdrawal'
            ]);

            return response()->json(['message' => 'Withdrawal Successful', 'wallet' => $adminWallet]);
        });
    }

    // 5. REFUND (Rider Quits -> Admin gives Cash back -> Rider Balance Decreases)
    public function refundRider(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $riderWallet = Wallet::where('user_id', $request->rider_id)->first();
            $adminWallet = Wallet::where('user_id', $request->admin_id)->first();

            if (!$riderWallet) {
                return response()->json(['error' => 'Rider wallet not found'], 404);
            }
            if (!$adminWallet) {
                return response()->json(['error' => 'Admin wallet not found'], 404);
            }

            // 1. Validation: Can admin pay?
            if ($adminWallet->cash_on_hand < $request->amount) {
                return response()->json(['error' => 'Not enough Cash on Hand to refund this amount'], 400);
            }

            // 2. Validation: Does rider own this money?
            if ($riderWallet->balance < $request->amount) {
                return response()->json(['error' => 'Rider does not have enough balance'], 400);
            }

            // 3. Execution
            // Rider loses digital balance (because they got cash)
            $riderWallet->decrement('balance', $request->amount);
            
            // Admin loses physical cash (because they paid the rider)
            $adminWallet->decrement('cash_on_hand', $request->amount);

            // 4. Record Transaction
            Transaction::create([
                'wallet_id' => $riderWallet->id,
                'admin_id' => $request->admin_id,
                'amount' => -$request->amount, // Negative because wallet balance decreases
                'balance_after' => $riderWallet->balance,
                'type' => 'refund',
                'description' => $request->reason ?? 'Rider Withdrawal'
            ]);

            return response()->json(['message' => 'Refund Successful']);
        });
    }

    // Helper: Create Wallet
    private function createEmptyWallet($userId) {
         return Wallet::create(['user_id' => $userId]);
    }

    // Helper: Send Notification (Safe wrapper)
    private function sendNotification($userId, $title, $body) {
        $user = User::find($userId);
        if ($user && $user->fcm_token) {
            try {
                $notify = new \App\Services\NotificationService();
                $notify->send($user->fcm_token, $title, $body);
            } catch (\Exception $e) {
                // Ignore notification errors so transaction doesn't fail
            }
        }
    }


}