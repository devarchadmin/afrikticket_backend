<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\Fundraising;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DonationController extends Controller
{
    public function store(Request $request, $fundraisingId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string'
        ]);

        $fundraising = Fundraising::findOrFail($fundraisingId);

        if ($fundraising->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'This fundraising campaign is not active'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $donation = Donation::create([
                'user_id' => $request->user()->id,
                'fundraising_id' => $fundraisingId,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'pending'
            ]);

            // Update fundraising current amount
            $fundraising->increment('current', $validated['amount']);

            // Check if goal is reached
            if ($fundraising->current >= $fundraising->goal) {
                $fundraising->update(['status' => 'completed']);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Donation made successfully',
                'data' => $donation
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to make donation'
            ], 500);
        }
    }


    public function myDonations()
    {
        $donations = Donation::where('user_id', Auth::id())
            ->with(['fundraising:id,title,goal,current,organization_id', 'fundraising.organization:id,name'])
            ->get()
            ->groupBy(function ($donation) {
                return $donation->created_at->format('Y-m');
            });

        $summary = [
            'total_donations' => $donations->flatten()->count(),
            'total_amount' => $donations->flatten()->sum('amount'),
            'average_donation' => $donations->flatten()->avg('amount'),
            'supported_causes' => $donations->flatten()->unique('fundraising_id')->count(),
            'monthly_stats' => $donations->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('amount'),
                    'average' => $group->avg('amount')
                ];
            })
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'donations' => $donations,
                'summary' => $summary
            ]
        ]);
    }
}