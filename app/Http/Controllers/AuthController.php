<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Admin;
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
            'role' => 'required|in:user,organization,admin',
            'phone' => 'nullable|string',
            'profile_image' => 'nullable|string',
            // Organization fields
            'org_name' => 'required_if:role,organization|string',
            'org_email' => 'required_if:role,organization|email',
            'org_phone' => 'required_if:role,organization|string',
            'org_description' => 'required_if:role,organization|string',
            'org_icd_document' => 'required_if:role,organization|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'org_commerce_register' => 'required_if:role,organization|file|mimes:pdf,jpg,jpeg,png|max:2048',
            // Admin fields
            'admin_role' => 'required_if:role,admin|in:super_admin,moderator',
            'admin_permissions' => 'nullable|array'
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
            
            // Handle file uploads
            $icdPath = $request->file('org_icd_document')->store('organizations/documents', 'public');
            $commercePath = $request->file('org_commerce_register')->store('organizations/documents', 'public');

            Organization::create([
                'name' => $validated['org_name'],
                'email' => $validated['org_email'],
                'phone' => $validated['org_phone'],
                'description' => $validated['org_description'],
                'user_id' => $user->id,
                'status' => 'pending',
                'icd_document' => $icdPath,
                'commerce_register' => $commercePath
            ]);
        } elseif ($validated['role'] === 'admin') {
            Admin::create([
                'user_id' => $user->id,
                'role' => $validated['admin_role'],
                'permissions' => $validated['admin_permissions'] ?? null
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful. Please login to continue.',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $validated['email'])->first();
        
        // Check if user exists and is active
        if (!$user || ($user->role === 'organization' && $user->organization->status !== 'approved')) {
            throw ValidationException::withMessages([
                'email' => ['Account not activated or pending approval.']
            ])->status(401);
        }

        if (!Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.']
            ])->status(401);
        }

        $user->load('organization');
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
    }
     
    
    // Get the authenticated user
    public function getUser(Request $request)
    {
        $user = $request->user();
        
        if ($user->role === 'organization') {
            $user->load(['organization']);
            $user->organization->showSensitiveData();
        }

        return response()->json([
            'status' => 'success',
            'data' => $user
        ]);
    }
    // Logout the authenticated user
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out'
        ]);
    }
}