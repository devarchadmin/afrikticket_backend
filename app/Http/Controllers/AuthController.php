<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Enum;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:user,organization',
            'phone' => 'nullable|string',
            'profile_image' => 'nullable|string',
            // Organization fields
            'org_name' => 'required_if:role,organization',
            'org_email' => 'required_if:role,organization|email',
            'org_phone' => 'required_if:role,organization',
            'org_description' => 'required_if:role,organization',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'profile_image' => $validated['profile_image'] ?? null,
        ]);

        if ($validated['role'] === 'organization') {
            Organization::create([
                'name' => $validated['org_name'],
                'email' => $validated['org_email'],
                'phone' => $validated['org_phone'],
                'description' => $validated['org_description'],
                'user_id' => $user->id,
                'status' => 'pending'
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.']
            ])->status(401);
        }

        $user = User::with('organization')->where('email', $validated['email'])->first();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
    }

    public function getUser(Request $request)
    {
        $user = $request->user()->load('organization');
        return response()->json([
            'status' => 'success',
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out'
        ]);
    }
}