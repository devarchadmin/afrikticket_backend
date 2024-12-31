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

    public function delete($id)
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
        if (Auth::user()->role !== 'organization') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $events = Event::with(['tickets'])
            ->where('organization_id', Auth::user()->organization->id)
            ->get()
            ->map(function ($event) {
                return [
                    'event' => $event,
                    'stats' => [
                        'total_tickets' => $event->tickets->count(),
                        'tickets_remaining' => $event->max_tickets - $event->tickets->count(),
                        'revenue' => $event->tickets->count() * $event->price,
                        'occupancy_rate' => ($event->tickets->count() / $event->max_tickets) * 100,
                        'is_sold_out' => $event->tickets->count() >= $event->max_tickets,
                        'status' => $event->date < now() ? 'past' : ($event->date->isToday() ? 'today' : 'upcoming')
                    ]
                ];
            });

        $totalRevenue = $events->sum(function ($event) {
            return $event['stats']['revenue'];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'events' => $events,
                'summary' => [
                    'total_events' => $events->count(),
                    'total_revenue' => $totalRevenue,
                    'active_events' => $events->where('event.status', 'active')->count(),
                    'upcoming_events' => $events->where('stats.status', 'upcoming')->count(),
                    'past_events' => $events->where('stats.status', 'past')->count()
                ]
            ]
        ]);
    }
    public function userEvents()
    {
        $user = auth()->user();
        $now = now();

        $events = Event::whereHas('tickets', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with([
                    'organization',
                    'tickets' => function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    }
                ])
            ->get()
            ->groupBy(function ($event) use ($now) {
                if ($event->date < $now) {
                    return 'past';
                } elseif ($event->date->isToday()) {
                    return 'present';
                } else {
                    return 'upcoming';
                }
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'past' => $events['past'] ?? [],
                'present' => $events['present'] ?? [],
                'upcoming' => $events['upcoming'] ?? []
            ]
        ]);
    }
}