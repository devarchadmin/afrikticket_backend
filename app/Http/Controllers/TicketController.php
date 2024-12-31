<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TicketController extends Controller
{
    public function store(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);
        $quantity = $request->input('quantity', 1); // Default to 1 if not specified

        // Check if tickets are still available
        $totalTicketsSold = Ticket::where('event_id', $eventId)->count();
        if ($totalTicketsSold + $quantity > $event->max_tickets) {
            return response()->json(['message' => 'Not enough tickets available'], 400);
        }

        $tickets = [];
        for ($i = 0; $i < $quantity; $i++) {
            // Generate JWT token
            $payload = [
                'sub' => Auth::id(),
                'event_id' => $eventId,
                'purchase_date' => now()->toDateTimeString(),
                'ticket_number' => $i + 1,
            ];
            $token = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

            // Create the ticket
            $ticket = Ticket::create([
                'event_id' => $eventId,
                'user_id' => Auth::id(),
                'price' => $event->price,
                'purchase_date' => now(),
                'status' => 'valid',
                'token' => $token,
            ]);

            $tickets[] = $ticket;
        }

        return response()->json(['status' => 'success', 'data' => $tickets], 201);
    }

    public function validateTicket(Request $request)
    {
        $token = $request->input('token');

        try {
            // Decode the JWT token
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));

            // Check if the ticket exists and is valid
            $ticket = Ticket::where('token', $token)->where('status', 'valid')->first();
            if (!$ticket) {
                return response()->json(['message' => 'Invalid or already used ticket'], 400);
            }

            // Mark the ticket as used
            $ticket->update(['status' => 'used']);

            return response()->json(['status' => 'success', 'message' => 'Ticket validated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid token'], 400);
        }
    }

    public function myTicket(Request $request)
    {
        $tickets = Ticket::where('user_id', Auth::id())
            ->with(['event.organization'])
            ->get()
            ->groupBy(function ($ticket) {
                return $ticket->event->date < now()
                    ? 'past'
                    : ($ticket->event->date->isToday() ? 'today' : 'upcoming');
            });

        $summary = [
            'total_tickets' => array_sum(array_map('count', $tickets->toArray())),
            'total_spent' => array_sum(array_map(function ($group) {
                return collect($group)->sum('price');
            }, $tickets->toArray())),
            'upcoming_events' => isset($tickets['upcoming']) ? count($tickets['upcoming']) : 0,
            'past_events' => isset($tickets['past']) ? count($tickets['past']) : 0,
            'today_events' => isset($tickets['today']) ? count($tickets['today']) : 0
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'tickets' => [
                    'past' => $tickets['past'] ?? [],
                    'today' => $tickets['today'] ?? [],
                    'upcoming' => $tickets['upcoming'] ?? []
                ],
                'summary' => $summary
            ]
        ]);
    }

}
