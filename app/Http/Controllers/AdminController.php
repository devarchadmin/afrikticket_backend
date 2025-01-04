<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Fundraising;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function getAllUsers()
    {
        $users = User::with(['organization', 'admin'])->get();
        return response()->json(['status' => 'success', 'data' => $users]);
    }

    public function getAllOrganizations()
    {
        $organizations = Organization::with(['user'])
            ->withCount(['events', 'fundraisings'])
            ->get()
            ->map(function ($org) {
                return [
                    'organization' => $org,
                    'stats' => [
                        'total_events' => $org->events_count,
                        'total_fundraisings' => $org->fundraisings_count,
                        'registration_date' => $org->created_at->format('Y-m-d')
                    ]
                ];
            });

        return response()->json(['status' => 'success', 'data' => $organizations], 200);
    }

    public function deleteOrganisation($id){
        
        $organization = Organization::find($id);
        if($organization){
            $organization->delete();
            return response()->json(['status'=> 'success',''=> $organization->id]);
        }else{
            return response()->json(['status'=> 'error','message'=> 'Organization not found'], 404);
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

    public function getPendingContent()
    {
        $pendingOrgs = Organization::where('status', 'pending')->with('user')->get();
        $pendingEvents = Event::where('status', 'pending')->with('organization')->get();
        $pendingFundraisings = Fundraising::where('status', 'pending')->with('organization')->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'organizations' => $pendingOrgs,
                'events' => $pendingEvents,
                'fundraisings' => $pendingFundraisings
            ]
        ], 200);
    }

    public function getPendingOrganizations()
    {
        $pendingOrgs = Organization::where('status', 'pending')
            ->with('user')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $pendingOrgs
        ], 200);
    }

    public function getPendingEvents()
    {
        $pendingEvents = Event::where('status', 'pending')
            ->with('organization')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $pendingEvents
        ], 200);
    }


    public function getPendingFundraisings()
    {
        $pendingFundraisings = Fundraising::where('status', 'pending')
            ->with('organization')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $pendingFundraisings
        ], 200);
    }

    public function reviewEvent(Request $request, $id)
    {
        $validated = $request->validate([
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