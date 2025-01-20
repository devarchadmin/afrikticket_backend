<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
                    'price' => $event->price,
                    'remaining_tickets' => $event->max_tickets - $ticketsSold,
                    'time_remaining' => now()->isBefore($event->date)
                        ? now()->diffForHumans($event->date, [
                            'parts' => 2,
                            'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE
                        ])
                        : 'Terminé',
                    'is_ongoing' => now()->between($event->date, $endTime),
                    'image' => $event->images->first()?->image_path,
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
                'time_remaining' => now()->isBefore($event->date)
                    ? now()->diffForHumans($event->date, [
                        'parts' => 2,
                        'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE
                    ])
                    : 'Terminé'
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

        // Allow both admin and organization owner
        if (Auth::user()->role !== 'admin' && 
            Auth::user()->organization?->id !== $event->organization_id) {
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
            'status' => 'sometimes|in:active,cancelled,pending',
            'images' => 'sometimes|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'remove_images' => 'sometimes|array',
            'remove_images.*' => 'exists:event_images,id'
        ]);

        DB::beginTransaction();
        try {
            // Check if status is being changed to 'active' by non-admin
            if (
                isset($validated['status']) &&
                $validated['status'] === 'active' &&
                Auth::user()->role !== 'admin'
            ) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only administrators can activate events'
                ], 403);
            }

            // If max_tickets is being reduced, check if it's still above sold tickets count
            if (isset($validated['max_tickets'])) {
                $soldTickets = $event->tickets()->count();
                if ($validated['max_tickets'] < $soldTickets) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot reduce max tickets below sold tickets count'
                    ], 400);
                }
            }

            // Update event
            $event->update($validated);

            // Remove images if specified
            if ($request->has('remove_images')) {
                $imagesToDelete = $event->images()->whereIn('id', $request->remove_images)->get();
                foreach ($imagesToDelete as $image) {
                    // Delete from storage
                    Storage::disk('public')->delete($image->image_path);
                    $image->delete();
                }
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

            // If date is changed, notify ticket holders
            if (isset($validated['date']) && $event->isDirty('date')) {
                // TODO: Implement notification system
                // $event->tickets()->with('user')->get()->each(function ($ticket) use ($event) {
                //     Notification::send($ticket->user, new EventDateChanged($event));
                // });
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Event updated successfully',
                'data' => $event->fresh(['images', 'organization'])
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

        // Allow both admin and organization owner
        if (Auth::user()->role !== 'admin' && 
            Auth::user()->organization?->id !== $event->organization_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $event->delete();

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
                'images' => function ($query) {
                    $query->where('is_main', true);
                },
                'tickets' => function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                }
            ])
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'date' => $event->date,
                    'location' => $event->location,
                    'price' => $event->price,
                    'organization' => $event->organization,
                    'main_image' => $event->images->first()?->image_path,
                    'tickets' => [
                        'count' => $event->tickets->count(),
                        'total_cost' => $event->tickets->count() * $event->price,
                        'status' => now()->gt($event->date) ? 'past' :
                            (now()->isSameDay($event->date) ? 'today' : 'upcoming')
                    ]
                ];
            })
            ->groupBy(function ($event) {
                return $event['tickets']['status'];
            });

        $summary = [
            'total_tickets' => $events->flatten(1)->sum('tickets.count'),
            'total_spent' => $events->flatten(1)->sum('tickets.total_cost'),
            'total_events' => $events->flatten(1)->count()
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'events' => [
                    'upcoming' => $events['upcoming'] ?? [],
                    'today' => $events['today'] ?? [],
                    'past' => $events['past'] ?? []
                ],
                'summary' => $summary
            ]
        ]);
    }

   
    
    public function getCalendarEvents(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated'
                ], 401);
            }
    
            $events = Event::whereHas('tickets', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with([
                'organization:id,name',
                'images' => function ($query) {
                    $query->where('is_main', true)->select('id', 'event_id', 'image_path');
                },
                'tickets' => function ($query) use ($user) {
                    $query->where('user_id', $user->id)->select('id', 'event_id', 'price');
                }
            ])
            ->select('id', 'title', 'description', 'date', 'duration', 'location', 'price', 'organization_id')
            ->get();
    
            $formattedEvents = $events->map(function ($event) {
                try {
                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'start' => Carbon::parse($event->date)->toIso8601String(),
                        'end' => $this->calculateEndTime($event->date, $event->duration)->toIso8601String(),
                        'allDay' => false,
                        'extendedProps' => [
                            'location' => $event->location,
                            'description' => $event->description,
                            'ticketCount' => $event->tickets->count(),
                            'totalCost' => $event->tickets->sum('price'),
                            'organization' => $event->organization?->name ?? 'Unknown',
                            'image' => $event->images->first()?->image_path,
                            'status' => now()->gt($event->date) ? 'past' : 
                                      (now()->isSameDay($event->date) ? 'today' : 'upcoming')
                        ]
                    ];
                } catch (\Exception $e) {
                    \Log::error('Error formatting event: ' . $e->getMessage(), [
                        'event_id' => $event->id,
                        'error' => $e->getMessage()
                    ]);
                    return null;
                }
            })->filter();
    
            return response()->json([
                'status' => 'success',
                'events' => $formattedEvents
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Calendar events error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching calendar events: ' . $e->getMessage()
            ], 500);
        }
    }
}