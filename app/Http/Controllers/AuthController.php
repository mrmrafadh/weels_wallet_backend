<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validate Input
        $request->validate([
            'mobile' => 'required',
            'password' => 'required',
        ]);

        // 2. Find User by Mobile
        $user = User::where('mobile', $request->mobile)->first();

        // 3. Check Password
        // We use Hash::check because passwords should be encrypted in the DB
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // 4. Success! Return User Data (and Token if using Sanctum)
        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'mobile' => $user->mobile
            ]
        ], 200);
    }
}