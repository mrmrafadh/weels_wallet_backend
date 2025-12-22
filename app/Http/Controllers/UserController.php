<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// --- ADD THESE TWO LINES ---
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
// ----------------------------
use App\Models\User;
use App\Models\Wallet;

class UserController extends Controller
{
    public function createRider(Request $request)
    {
        // 1. Validate Input
        $request->validate([
            'name' => 'required',
            'mobile' => 'required|unique:users,mobile',
        ]);

        return DB::transaction(function () use ($request) {
            
            // 2. Generate Dummy Email
            $dummyEmail = $request->mobile . '@rider.weels';

            // 3. Create Rider
            $rider = User::create([
                'name' => $request->name,
                'mobile' => $request->mobile,
                'email' => $dummyEmail,
                'password' => Hash::make($request->name), // Securely hash the password
                'role' => 'rider'
            ]);

            // 4. Create Wallet
            Wallet::create([
                'user_id' => $rider->id,
                'balance' => 0,
                'cash_on_hand' => 0,
                'earnings' => 0
            ]);

            return response()->json([
                'message' => 'Rider created successfully',
                'rider' => $rider
            ]);
        });
    }
}