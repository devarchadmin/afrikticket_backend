<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Event;
use App\Models\Admin;
use App\Models\Organization;
use App\Models\Fundraising;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Storage;

class AdminController extends Controller
{
    public function getAllUsers(Request $request)
    {
        $perPage = $request->input('per_page', 10); // Default 10 items per page
        $users = User::with(['organization', 'admin'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    }

    public function createAdmin(Request $request)
    {
        // Check if current user is super_admin
        if (!$request->user()->admin || $request->user()->admin->role !== 'super_admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only super admins can create new administrators'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string',
            'admin_role' => 'required|in:moderator,super_admin',
            'permissions' => 'nullable|array',
            'permissions.*' => 'nullable|in:users,organizations,events,fundraising,settings'
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'admin',
                'phone' => $validated['phone'] ?? null,
                'status' => 'active'
            ]);

            Admin::create([
                'user_id' => $user->id,
                'role' => $validated['admin_role'],
                'permissions' => $validated['permissions'] ?? []
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Administrator created successfully',
                'data' => $user->load('admin')
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create administrator'
            ], 500);
        }
    }

    public function updateAdminRole(Request $request, $id)
    {
        // Check if current user is super_admin
        if (!$request->user()->admin || $request->user()->admin->role !== 'super_admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only super admins can modify administrator roles'
            ], 403);
        }

        $validated = $request->validate([
            'admin_role' => 'required|in:moderator,super_admin',
            'permissions' => 'nullable|array',
            'permissions.*' => 'nullable|in:users,organizations,events,fundraising,settings'
        ]);

        $admin = Admin::where('user_id', $id)->firstOrFail();

        $admin->update([
            'role' => $validated['admin_role'],
            'permissions' => $validated['permissions'] ?? []
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Administrator role updated successfully',
            'data' => $admin->load('user')
        ]);
    }

    public function getAllOrganizations(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $organizations = Organization::with(['user'])
            ->withCount(['events', 'fundraisings'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->through(function ($org) {
                return [
                    'organization' => $org,
                    'stats' => [
                        'total_events' => $org->events_count,
                        'total_fundraisings' => $org->fundraisings_count,
                        'registration_date' => $org->created_at->format('Y-m-d')
                    ]
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $organizations
        ]);
    }

    public function deleteOrganisation($id)
    {

        $organization = Organization::find($id);
        if ($organization) {
            $organization->delete();
            return response()->json(['status' => 'success', '' => $organization->id]);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Organization not found'], 404);
        }

    }

    public function updateOrganizationStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'reason' => 'required_if:status,rejected|string'
        ]);

        $organization = Organization::findOrFail($id);

        DB::beginTransaction();
        try {
            $organization->update([
                'status' => $validated['status'],
                'rejection_reason' => $validated['status'] === 'rejected' ? $validated['reason'] : null  
            ]);

            // If approved, activate the user account
            if ($validated['status'] === 'approved') {
                $organization->user->update(['status' => 'active']);
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => "Organization {$validated['status']} successfully"
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Failed to update organization status'], 500);
        }
    }

    public function getPendingContent(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        
        $pendingOrgs = Organization::where('status', 'pending')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        $pendingEvents = Event::where('status', 'pending')
            ->with('organization')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        $pendingFundraisings = Fundraising::where('status', 'pending')
            ->with('organization')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => [
                'organizations' => $pendingOrgs,
                'events' => $pendingEvents,
                'fundraisings' => $pendingFundraisings
            ]
        ], 200);
    }

    public function getPendingOrganizations(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $pendingOrgs = Organization::where('status', 'pending')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $pendingOrgs
        ], 200);
    }

    public function getPendingEvents(Request $request) 
    {
        $perPage = $request->input('per_page', 10);
        $pendingEvents = Event::where('status', 'pending')
            ->with('organization')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $pendingEvents
        ], 200);
    }


    public function getPendingFundraisings(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $pendingFundraisings = Fundraising::where('status', 'pending')
            ->with('organization')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $pendingFundraisings
        ], 200);
    }

    public function reviewEvent(Request $request, $id)
    {
        $validated = $request->validate(rules: [
            'status' => 'required|in:active,rejected',
            'reason' => 'required_if:status,rejected|string'
        ]);

        $event = Event::findOrFail($id);
        $event->update([
            'status' => $validated['status'],
            'rejection_reason' => $validated['status'] === 'rejected' ? $validated['reason'] : null
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Event {$validated['status']} successfully"
        ]);
    }

    public function reviewFundraising(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,rejected',
            'reason' => 'required_if:status,rejected|string'
        ]);

        $fundraising = Fundraising::findOrFail($id);
        $fundraising->update([
            'status' => $validated['status'],
            'rejection_reason' => $validated['status'] === 'rejected' ? $validated['reason'] : null
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Fundraising {$validated['status']} successfully"
        ]);
    }

    public function getDashboardStats()
    {
        $stats = [
            'users' => [
                'total' => User::count(),
                'organizations' => User::where('role', 'organization')->count(),
                'regular_users' => User::where('role', 'user')->count()
            ],
            'content' => [
                'total_events' => Event::count(),
                'active_events' => Event::where('status', 'active')->count(),
                'total_fundraisings' => Fundraising::count(),
                'active_fundraisings' => Fundraising::where('status', 'active')->count()
            ],
            'pending' => [
                'organizations' => Organization::where('status', 'pending')->count(),
                'events' => Event::where('status', 'pending')->count(),
                'fundraisings' => Fundraising::where('status', 'pending')->count()
            ]
        ];

        return response()->json(['status' => 'success', 'data' => $stats]);
    }

}