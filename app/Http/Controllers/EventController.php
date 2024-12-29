<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::with(['organization'])->where('status', 'active')->get();
        return response()->json(['status' => 'success', 'data' => $events], 201);
    }

    public function store(Request $request)
    {
        if (Auth::user()->role !== 'organization' && Auth::user()->role !== 'admin') {
            return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized'
            ], 403);
        }
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'date' => 'required|date|after:now',
            'location' => 'required|string',
            'max_tickets' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            // 'fundraising_id' => 'nullable|exists:fundraisings,id'
            // 'fundraising_id' => 'nullable'
        ]);

        $validated['organization_id'] = Auth::user()->organization->id;
        $event = Event::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Event created successfully',
            'data' => $event
        ], 201);
    }

    public function show(Event $id)

    {
        $event = Event::find($id);
        $event->load(['organization']);
        //add fundraising and tickets later when it is implemented
        // $event->load(['organization', 'fundraising', 'tickets']);
        return response()->json(['status' => 'success', 'data' => $event], 201);
    }

    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        
        if (Auth::user()->role !== 'admin' && Auth::user()->organization->id !== $event->organization_id) {
            return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'date' => 'sometimes|date|after:now',
            'location' => 'sometimes|string',
            'max_tickets' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:active,cancelled'
        ]);

        $event->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Event updated successfully',
            'data' => $event
        ]);
    }

    public function delete( $id)
    {
        // return response()->json(["hello"]);
        $event = Event::findOrFail($id);
        
        if (Auth::user()->role !== 'admin' && Auth::user()->organization->id !== $event->organization_id) {
            return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized'
            ], 403);
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Event deleted successfully'
        ]);
    }
    public function organizationEvents()
    {
        // if (Auth::user()->role !== 'organization') {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Unauthorized'
        //     ], 403);
        // }

        $events = Event::where('organization_id', Auth::user()->organization->id)->get();
        return response()->json([
            'status' => 'success',
            'data' => $events
        ]);
    }
}