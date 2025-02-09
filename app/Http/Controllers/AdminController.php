<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Event;
use App\Models\Admin;
use App\Models\Organization;
use App\Models\Fundraising;
use App\Models\Ticket;
use App\Models\Donation;
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
        ->paginate($perPage)
        ->through(function ($org) {
            return $org->showSensitiveData();
        });

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

    public function getEventWithUsers($id)
    {
        $event = Event::with(['organization', 'images'])
            ->withCount('tickets')
            ->findOrFail($id);

        $ticketUsers = $event->tickets()
            ->with('user')
            ->select('user_id', DB::raw('count(*) as tickets_count'))
            ->groupBy('user_id')
            ->get()
            ->map(function ($ticket) use ($event) {
                return [
                    'user' => $ticket->user,
                    'tickets_purchased' => $ticket->tickets_count,
                    'total_spent' => $ticket->tickets_count * $event->price,
                    'purchase_date' => $ticket->created_at
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'event' => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'date' => $event->date,
                    'price' => $event->price,
                    'location' => $event->location,
                    'organization' => $event->organization,
                    'main_image' => $event->images->first()?->image_path,
                    'category' => $event->category,
                ],
                'stats' => [
                    'total_tickets_sold' => $event->tickets_count,
                    'total_revenue' => $event->tickets_count * $event->price,
                    'unique_buyers' => $ticketUsers->count()
                ],
                'ticket_buyers' => $ticketUsers
            ]
        ]);
    }

    public function getFundraisingWithUsers($id)
    {
        $fundraising = Fundraising::with(['organization', 'images'])
            ->withCount('donations')
            ->withSum('donations', 'amount')
            ->findOrFail($id);

        $donorUsers = $fundraising->donations()
            ->with('user')
            ->select('user_id', 
                DB::raw('count(*) as donations_count'),
                DB::raw('sum(amount) as total_donated'))
            ->groupBy('user_id')
            ->get()
            ->map(function ($donation) {
                return [
                    'user' => $donation->user,
                    'donations_count' => $donation->donations_count,
                    'total_donated' => $donation->total_donated,
                    'last_donation_date' => $donation->created_at
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'fundraising' => [
                    'id' => $fundraising->id,
                    'title' => $fundraising->title,
                    'description' => $fundraising->description,
                    'goal' => $fundraising->goal,
                    'current' => $fundraising->current,
                    'organization' => $fundraising->organization,
                    'main_image' => $fundraising->images->first()?->image_path,
                    'category' => $fundraising->category,
                ],
                'stats' => [
                    'total_donations' => $fundraising->donations_count,
                    'total_raised' => $fundraising->donations_sum_amount,
                    'unique_donors' => $donorUsers->count(),
                    'progress_percentage' => $fundraising->goal > 0 
                        ? round(($fundraising->current / $fundraising->goal) * 100, 2)
                        : 0
                ],
                'donors' => $donorUsers
            ]
        ]);
    }

    public function getUserActivity($id)
    {
        $user = User::findOrFail($id);

        // Get user's tickets with event details
        $tickets = Ticket::where('user_id', $id)
            ->with(['event' => function($query) {
                $query->with(['organization:id,name', 'images']);
            }])
            ->get()
            ->map(function ($ticket) {
                return [
                    'ticket_id' => $ticket->id,
                    'event' => [
                        'id' => $ticket->event->id,
                        'title' => $ticket->event->title,
                        'date' => $ticket->event->date,
                        'price' => $ticket->event->price,
                        'location' => $ticket->event->location,
                        'organization' => $ticket->event->organization->name,
                        'image' => $ticket->event->images->first()?->image_path,
                        'category' => $ticket->event->category
                    ],
                    'purchase_date' => $ticket->created_at,
                    'status' => now()->gt($ticket->event->date) ? 'past' : 
                               (now()->isSameDay($ticket->event->date) ? 'today' : 'upcoming')
                ];
            });

        // Get user's donations with fundraising details
        $donations = Donation::where('user_id', $id)
            ->with(['fundraising' => function($query) {
                $query->with(['organization:id,name', 'images']);
            }])
            ->get()
            ->map(function ($donation) {
                return [
                    'donation_id' => $donation->id,
                    'amount' => $donation->amount,
                    'fundraising' => [
                        'id' => $donation->fundraising->id,
                        'title' => $donation->fundraising->title,
                        'goal' => $donation->fundraising->goal,
                        'current' => $donation->fundraising->current,
                        'organization' => $donation->fundraising->organization->name,
                        'image' => $donation->fundraising->images->first()?->image_path,
                        'category' => $donation->fundraising->category
                    ],
                    'donation_date' => $donation->created_at,
                    'status' => $donation->fundraising->current >= $donation->fundraising->goal ? 'completed' : 
                               ($donation->fundraising->status === 'active' ? 'active' : 'cancelled')
                ];
            });

        // Calculate summary statistics
        $summary = [
            'total_tickets' => $tickets->count(),
            'total_events_attended' => $tickets->unique('event.id')->count(),
            'total_spent_tickets' => $tickets->sum('event.price'),
            'total_donations' => $donations->count(),
            'total_causes_supported' => $donations->unique('fundraising.id')->count(),
            'total_donated' => $donations->sum('amount'),
            'average_donation' => $donations->avg('amount'),
            'registration_date' => $user->created_at,
            'last_activity' => max(
                $tickets->max('purchase_date')?->format('Y-m-d H:i:s'),
                $donations->max('donation_date')?->format('Y-m-d H:i:s')
            )
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'role' => $user->role
                ],
                'summary' => $summary,
                'activity' => [
                    'tickets' => $tickets->groupBy('status'),
                    'donations' => $donations->groupBy('status')
                ]
            ]
        ]);
    }

}