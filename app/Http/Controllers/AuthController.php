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
            'role' => 'required|in:user,organization',
            'phone' => 'nullable|string',
            'profile_image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            // Organization fields
            'org_name' => 'required_if:role,organization|string',
            'org_email' => 'required_if:role,organization|email',
            'org_phone' => 'required_if:role,organization|string',
            'org_description' => 'required_if:role,organization|string',
            'org_icd_document' => 'required_if:role,organization|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'org_commerce_register' => 'required_if:role,organization|file|mimes:pdf,jpg,jpeg,png|max:2048',
            // Admin fields
            // 'admin_role' => 'required_if:role,admin|in:super_admin,moderator',
            // 'admin_permissions' => 'nullable|array'
        ]);

        // Handle profile image upload
        $profile_image_path = null;
        if ($request->hasFile('profile_image')) {
            $profile_image_path = $request->file('profile_image')
                ->store('profile-images', 'public');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'profile_image' => $profile_image_path,
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
        }
        // elseif ($validated['role'] === 'admin') {
        //     Admin::create([
        //         'user_id' => $user->id,
        //         'role' => $validated['admin_role'],
        //         'permissions' => $validated['admin_permissions'] ?? null
        //     ]);
        // }

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful. Please login to continue.',
            'user' => $user,
            'token' => $user->createToken('auth-token')->plainTextToken

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


    public function getUser(int $id)
    {
        if (!$id) {
            return response()->json([
                'message' => 'User ID is required'
            ], 400);
        }
        $user = User::findOrFail($id);
        $authUser = Auth::user();
        if ($authUser->id !== $id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: Cannot access other user profiles'
            ], 403);
        }

        if ($user) {
            return response()->json([
                'user' => $user,
                'message' => 'User fetched successfully'
            ], 200);
        }

        return response()->json([
            'message' => 'User not found'
        ], 404);
    }


    // Get the authenticated user
    public function getAuthUser(Request $request)
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

    public function updateUser(Request $request, int $id)
    {
        if (!$id) {
            return response()->json([
                'message' => 'User ID is required'
            ], 400);
        }
        $user = User::findOrFail($id);
        $authUser = Auth::user();
        if ($authUser->id !== $id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: Cannot access other user profiles'
            ], 403);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ]);
        $user->update($validated);
        if ($user) {
            return response()->json([
                'user' => $user,
                'message' => 'User updated successfully'
            ], 200);
        }
        return response()->json([
            'message' => 'User not found'
        ], 404);
    }

    public function updateUserPassword(Request $request, int $id)
    {
        if (!$id) {
            return response()->json([
                'message' => 'User ID is required'
            ], 400);
        }

        $user = User::findOrFail($id);

        $authUser = Auth::user();
        if ($authUser->id !== $id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: Cannot access other user profiles'
            ], 403);
        }

        $validated = $request->validate([
            'currentPassword' => 'required|string|min:8',
            'newPassword' => 'required|string|min:8',
            'confirmPassword' => 'required|string|min:8|same:newPassword',
        ]);

        if (!Hash::check($validated['currentPassword'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($validated['newPassword'])
        ]);

        return response()->json([
            'message' => 'Password updated successfully'
        ], 200);
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


