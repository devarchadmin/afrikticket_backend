<?php

namespace App\Http\Controllers;

use App\Models\Fundraising;
use Illuminate\Http\Request;

class FundraisingController extends Controller
{
    public function index()
{
    $fundraisings = Fundraising::with(['organization', 'donations'])
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
        ]);

        $validated['organization_id'] = $request->user()->organization->id;
        $fundraising = Fundraising::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Fundraising campaign created successfully',
            'data' => $fundraising
        ], 201);
    }

    public function show($id)
    {
        $fundraising = Fundraising::with(['organization', 'donations'])
            ->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $fundraising]);
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
            'status' => 'sometimes|in:active,completed,cancelled'
        ]);

        $fundraising->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Fundraising updated successfully',
            'data' => $fundraising
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
        
        return response()->json(['status' => 'success', 'data' => $fundraisings]);
    }
}