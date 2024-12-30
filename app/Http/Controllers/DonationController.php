<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\Fundraising;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}