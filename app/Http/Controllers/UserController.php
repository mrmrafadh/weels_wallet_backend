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

    public function editUserProf(Request $request)
    {
        // 1. Validate Input
        $request->validate([
            // Check if this ID exists in the DB
            'id' => 'required|exists:users,id', 
            
            'name' => 'required|string',
            
            // IGNORE the current user's ID when checking for unique mobile
            'mobile' => 'required|unique:users,mobile,' . $request->id,
            
            // Optional: Handle email similarly
            'email' => 'nullable|email|unique:users,email,' . $request->id, 
            
            // Optional: Validate password only if provided
            'password' => 'nullable'
        ]);

        return DB::transaction(function () use ($request) {
            
            // 2. Find the existing user
            // findOrFail throws a 404 error automatically if not found
            $rider = User::findOrFail($request->id);

            // 3. Prepare Data safely (Whitelist approach)
            $updateData = $request->only(['name', 'mobile', 'email']);

            // 4. Handle Password Securely (Only update if user sent a new one)
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            // 5. Update the User
            $rider->update($updateData);

            return response()->json([
                'message' => 'Profile updated successfully',
                'rider' => $rider
            ]);
        });
    }
}