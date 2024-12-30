<?php

namespace App\Http\Controllers;

use App\Models\Fundraising;
use Illuminate\Http\Request;

class FundraisingController extends Controller
{
    public function index()
    {
        $fundraisings = Fundraising::with('organization')
            ->where('status', 'active')
            ->get();
        
        return response()->json(['status' => 'success', 'data' => $fundraisings]);
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
}