<?php

namespace App\Http\Controllers;

use App\Models\Fundraising;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FundraisingController extends Controller
{
    public function index()
    {
        $fundraisings = Fundraising::with(['organization', 'donations', 'images'])
            ->where('status', 'active')
            ->get()
            ->map(function ($fundraising) {
                return [
                    'fundraising' => $fundraising,
                    'stats' => [
                        'total_donors' => $fundraising->donations->count(),
                        'total_raised' => $fundraising->donations->sum('amount'),
                        'progress_percentage' => $fundraising->goal > 0 
                            ? round(($fundraising->donations->sum('amount') / $fundraising->goal) * 100, 2)
                            : 0,
                        'remaining_amount' => max(0, $fundraising->goal - $fundraising->donations->sum('amount')),
                        'average_donation' => $fundraising->donations->count() > 0 
                            ? round($fundraising->donations->avg('amount'), 2)
                            : 0
                    ]
                ];
            });

        $summary = [
            'total_fundraisings' => $fundraisings->count(),
            'total_raised' => $fundraisings->sum('stats.total_raised'),
            'total_donors' => $fundraisings->sum('stats.total_donors'),
            'average_progress' => $fundraisings->avg('stats.progress_percentage')
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'fundraisings' => $fundraisings,
                'summary' => $summary
            ]
        ]);
    }

public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'goal' => 'required|numeric|min:0',
        'images' => 'required|array|min:1',
        'images.*' => 'image|mimes:jpeg,png,jpg|max:2048'
    ]);

    DB::beginTransaction();
    try {
        $fundraising = Fundraising::create([
            ...$validated,
            'organization_id' => $request->user()->organization->id,
            'status' => 'pending', 
            'current' => 0
        ]);

        foreach ($request->file('images') as $index => $image) {
            $path = $image->store('fundraising/images', 'public');
            $fundraising->images()->create([
                'image_path' => $path,
                'is_main' => $index === 0
            ]);
        }

        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Fundraising created successfully',
            'data' => $fundraising->load('images')
        ], 201);
    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to create fundraising'
        ], 500);
    }
}

public function show($id)
{
    $fundraising = Fundraising::with(['organization', 'donations', 'images'])
        ->findOrFail($id);

    $stats = [
        'total_donors' => $fundraising->donations->count(),
        'total_raised' => $fundraising->donations->sum('amount'),
        'progress_percentage' => $fundraising->goal > 0 
            ? round(($fundraising->donations->sum('amount') / $fundraising->goal) * 100, 2)
            : 0,
        'remaining_amount' => max(0, $fundraising->goal - $fundraising->donations->sum('amount')),
        'average_donation' => $fundraising->donations->count() > 0 
            ? round($fundraising->donations->avg('amount'), 2)
            : 0,
        'recent_donations' => $fundraising->donations()
            ->latest()
            ->take(5)
            ->get()
    ];

    return response()->json([
        'status' => 'success',
        'data' => [
            'fundraising' => $fundraising,
            'stats' => $stats
        ]
    ]);
}

public function update(Request $request, $id)
{
    $fundraising = Fundraising::findOrFail($id);

    if ($request->user()->organization->id !== $fundraising->organization_id) {
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 403);
    }

    $validated = $request->validate([
        'title' => 'sometimes|string|max:255',
        'description' => 'sometimes|string',
        'goal' => 'sometimes|numeric|min:0',
        'status' => 'sometimes|in:active,completed,cancelled',
        'images' => 'sometimes|array',
        'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        'remove_images' => 'sometimes|array',
        'remove_images.*' => 'exists:fundraising_images,id'
    ]);

    DB::beginTransaction();
    try {
        $fundraising->update($validated);

        if ($request->has('remove_images')) {
            $fundraising->images()
                ->whereIn('id', $request->remove_images)
                ->delete();
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('fundraising/images', 'public');
                $fundraising->images()->create([
                    'image_path' => $path,
                    'is_main' => false
                ]);
            }
        }

        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Fundraising updated successfully',
            'data' => $fundraising->load('images')
        ]);
    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update fundraising'
        ], 500);
    }
}

public function delete($id)
{
    $fundraising = Fundraising::findOrFail($id);

    if (Auth::user()->role !== 'admin' && 
        Auth::user()->organization->id !== $fundraising->organization_id) {
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 403);
    }

    $fundraising->delete();

    return response()->json([
        'status' => 'success',
        'message' => 'Fundraising deleted successfully'
    ]);
}
public function organizationFundraisings(Request $request)
{
    $fundraisings = Fundraising::withCount('donations')
        ->withSum('donations', 'amount')
        ->where('organization_id', $request->user()->organization->id)
        ->get()
        ->map(function ($fundraising) {
            return [
                'id' => $fundraising->id,
                'title' => $fundraising->title,
                'description' => $fundraising->description,
                'goal' => $fundraising->goal,
                'status' => $fundraising->status,
                'stats' => [
                    'total_donors' => $fundraising->donations_count,
                    'total_raised' => $fundraising->donations_sum_amount ?? 0,
                    'progress_percentage' => $fundraising->goal > 0 
                        ? round(($fundraising->donations_sum_amount ?? 0) / $fundraising->goal * 100, 2) 
                        : 0,
                    'remaining_amount' => max(0, $fundraising->goal - ($fundraising->donations_sum_amount ?? 0))
                ],
                'created_at' => $fundraising->created_at,
                'updated_at' => $fundraising->updated_at
            ];
        });

    $summary = [
        'total_fundraisings' => $fundraisings->count(),
        'total_goal_amount' => $fundraisings->sum('goal'),
        'total_raised' => $fundraisings->sum('stats.total_raised'),
        'total_donors' => $fundraisings->sum('stats.total_donors'),
        'average_progress' => $fundraisings->avg('stats.progress_percentage'),
        'total_remaining' => $fundraisings->sum('stats.remaining_amount'),
        'active_fundraisings' => $fundraisings->where('status', 'active')->count()
    ];
    
    return response()->json([
        'status' => 'success', 
        'data' => [
            'fundraisings' => $fundraisings,
            'summary' => $summary
        ]
    ]);
}

public function userFundraisings()
{
    $user = auth()->user();
    
    $fundraisings = Fundraising::whereHas('donations', function ($query) use ($user) {
        $query->where('user_id', $user->id);
    })
    ->with([
        'organization',
        'images' => function($query) {
            $query->where('is_main', true);
        },
        'donations' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }
    ])
    ->get()
    ->map(function ($fundraising) {
        return [
            'id' => $fundraising->id,
            'title' => $fundraising->title,
            'description' => $fundraising->description,
            'goal' => $fundraising->goal,
            'current' => $fundraising->current,
            'organization' => $fundraising->organization,
            'main_image' => $fundraising->images->first()?->image_path,
            'progress_percentage' => $fundraising->goal > 0 
                ? round(($fundraising->current / $fundraising->goal) * 100, 2)
                : 0,
            'donations' => [
                'count' => $fundraising->donations->count(),
                'total_amount' => $fundraising->donations->sum('amount'),
                'status' => $fundraising->current >= $fundraising->goal ? 'completed' : 
                           ($fundraising->status === 'active' ? 'active' : 'cancelled')
            ]
        ];
    })
    ->groupBy(function ($fundraising) {
        return $fundraising['donations']['status'];
    });

    $summary = [
        'total_donations' => $fundraisings->flatten(1)->sum('donations.count'),
        'total_contributed' => $fundraisings->flatten(1)->sum('donations.total_amount'),
        'total_fundraisings' => $fundraisings->flatten(1)->count()
    ];

    return response()->json([
        'status' => 'success',
        'data' => [
            'fundraisings' => [
                'active' => $fundraisings['active'] ?? [],
                'completed' => $fundraisings['completed'] ?? [],
                'cancelled' => $fundraisings['cancelled'] ?? []
            ],
            'summary' => $summary
        ]
    ]);
}
}

