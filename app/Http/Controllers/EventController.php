<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    private function calculateEndTime($startDate, $duration)
{
    preg_match('/(\d+)\s*(hour|day|week)s?/', $duration, $matches);
    if (count($matches) >= 3) {
        $value = (int) $matches[1];
        $unit = $matches[2];
        return \Carbon\Carbon::parse($startDate)->add(
            $unit === 'hour' ? $value . ' hours' : 
            ($unit === 'day' ? $value . ' days' : $value . ' weeks')
        );
    }
    return \Carbon\Carbon::parse($startDate)->addHours(2); // Default 2 hours
}
    public function index()
{
    $events = Event::with(['organization'])
        ->where('status', 'active') // Only show active events
        ->get()
        ->map(function ($event) {
            $ticketsSold = $event->tickets()->count();
            $endTime = $this->calculateEndTime($event->date, $event->duration);
            
            return [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'start_date' => $event->date,
                'end_date' => $endTime,
                'duration' => $event->duration,
                'location' => $event->location,
                'remaining_tickets' => $event->max_tickets - $ticketsSold,
                'time_remaining' => now()->diffForHumans($event->date, ['parts' => 2]),
                'is_ongoing' => now()->between($event->date, $endTime),
                'organization' => $event->organization
            ];
        });
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
            'duration' => 'required|numeric|min:0',
            'images' => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $validated['organization_id'] = Auth::user()->organization->id;
        $validated['status'] = 'pending'; // Add pending status
        
        DB::beginTransaction();
        try {
            $event = Event::create($validated);

            // Handle image uploads
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('events/images', 'public');
                $event->images()->create([
                    'image_path' => $path,
                    'is_main' => $index === 0 // First image is main
                ]);
            }

            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Event created successfully',
                'data' => $event->load('images')
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create event'
            ], 500);
        }
    }

    public function show($id)
    {
        $event = Event::findOrFail($id);
        $event->load(['organization', 'tickets', 'images']);
        
        $endTime = $this->calculateEndTime($event->date, $event->duration);
        $ticketsSold = $event->tickets->count();
    
        $eventData = [
            'event' => $event,
            'timing' => [
                'start_date' => $event->date,
                'end_date' => $endTime,
                'duration' => $event->duration,
                'is_ongoing' => now()->between($event->date, $endTime),
                'time_remaining' => now()->diffForHumans($event->date, ['parts' => 2])
            ],
            'tickets' => [
                'sold' => $ticketsSold,
                'remaining' => $event->max_tickets - $ticketsSold,
                'total' => $event->max_tickets
            ],
            'images' => [
                'main' => $event->images->where('is_main', true)->first(),
                'gallery' => $event->images->where('is_main', false)->values()
            ]
        ];
    
        return response()->json(['status' => 'success', 'data' => $eventData], 200);
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
            'duration' => 'sometimes|numeric|min:0',
            'images' => 'sometimes|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'remove_images' => 'sometimes|array',
            'remove_images.*' => 'exists:event_images,id'
        ]);

        DB::beginTransaction();
        try {
            $event->update($validated);

            // Remove images if specified
            if ($request->has('remove_images')) {
                $event->images()->whereIn('id', $request->remove_images)->delete();
            }

            // Add new images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('events/images', 'public');
                    $event->images()->create([
                        'image_path' => $path,
                        'is_main' => false
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Event updated successfully',
                'data' => $event->load('images')
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update event'
            ], 500);
        }
    }

    public function delete($id)
    {
        
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

        $events = Event::with(['tickets', 'images'])
            ->where('organization_id', Auth::user()->organization->id)
            ->get()
            ->map(function ($event) {
                return [
                    'event' => $event,
                    'status' => $event->status, // Add status to response
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
                'images',
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