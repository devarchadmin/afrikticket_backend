<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\Fundraising;
use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class OrganizationController extends Controller
{

    //time should be either all, year, month, week
    //send it in the link as a query parameter e.g. /organization/dashboard?time_filter=week
    public function getDashboardStats(Request $request)
    {
        $organizationId = Auth::user()->organization->id;
        $timeFilter = $request->input('time_filter', 'all'); // all, year, month, week

        // Build date constraint based on time filter
        $dateConstraint = $this->getDateConstraint($timeFilter);

        // Get events statistics
        $events = Event::where('organization_id', $organizationId)
            ->when($dateConstraint, function ($query, $date) {
                return $query->where('created_at', '>=', $date);
            })
            ->withCount(['tickets as sold_tickets'])
            ->get();

        // Get fundraising statistics
        $fundraisings = Fundraising::where('organization_id', $organizationId)
            ->when($dateConstraint, function ($query, $date) {
                return $query->where('created_at', '>=', $date);
            })
            ->withCount('donations')
            ->get();

        // Calculate events stats
        $eventsStats = [
            'total_events' => $events->count(),
            'active_events' => $events->where('status', 'active')->count(),
            'pending_events' => $events->where('status', 'pending')->count(),
            'cancelled_events' => $events->where('status', 'cancelled')->count(),
            'total_tickets_sold' => $events->sum('sold_tickets'),
            'total_revenue' => $events->sum(function ($event) {
                return $event->sold_tickets * $event->price;
            }),
            'average_occupancy' => $events->avg(function ($event) {
                return $event->max_tickets > 0
                    ? ($event->sold_tickets / $event->max_tickets) * 100
                    : 0;
            }),
            'events_by_status' => [
                'upcoming' => $events->where('date', '>', now())->count(),
                'ongoing' => $events->where('date', '<=', now())->where('end_date', '>=', now())->count(),
                'completed' => $events->where('date', '<', now())->count()
            ]
        ];

        // Calculate fundraising stats
        $fundraisingStats = [
            'total_fundraisings' => $fundraisings->count(),
            'active_fundraisings' => $fundraisings->where('status', 'active')->count(),
            'completed_fundraisings' => $fundraisings->where('status', 'completed')->count(),
            'total_donations' => $fundraisings->sum('donations_count'),
            'total_raised' => $fundraisings->sum('current'),
            'average_goal_completion' => $fundraisings->avg(function ($fundraising) {
                return $fundraising->goal > 0
                    ? ($fundraising->current / $fundraising->goal) * 100
                    : 0;
            }),
            'fundraisings_by_status' => [
                'active' => $fundraisings->where('status', 'active')->count(),
                'completed' => $fundraisings->where('status', 'completed')->count(),
                'cancelled' => $fundraisings->where('status', 'cancelled')->count()
            ]
        ];

        // Get revenue trends
        $revenueTrends = $this->getRevenueTrends($organizationId, $timeFilter);

        // Get top performing content
        $topPerformers = [
            'events' => $events->sortByDesc('sold_tickets')->take(5)
                ->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'tickets_sold' => $event->sold_tickets,
                        'revenue' => $event->sold_tickets * $event->price,
                        'occupancy_rate' => $event->max_tickets > 0
                            ? ($event->sold_tickets / $event->max_tickets) * 100
                            : 0
                    ];
                }),
            'fundraisings' => $fundraisings->sortByDesc('current')->take(5)
                ->map(function ($fundraising) {
                    return [
                        'id' => $fundraising->id,
                        'title' => $fundraising->title,
                        'raised' => $fundraising->current,
                        'goal_completion' => $fundraising->goal > 0
                            ? ($fundraising->current / $fundraising->goal) * 100
                            : 0,
                        'donors_count' => $fundraising->donations_count
                    ];
                })
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'events_stats' => $eventsStats,
                'fundraising_stats' => $fundraisingStats,
                'revenue_trends' => $revenueTrends,
                'top_performers' => $topPerformers,
                'time_period' => $timeFilter
            ]
        ]);
    }

    private function getDateConstraint($timeFilter)
    {
        switch ($timeFilter) {
            case 'week':
                return Carbon::now()->subWeek();
            case 'month':
                return Carbon::now()->subMonth();
            case 'year':
                return Carbon::now()->subYear();
            default:
                return null;
        }
    }


    private function getRevenueTrends($organizationId, $timeFilter)
    {
        // Modify tickets query to properly group by date
        $query = DB::table('tickets')
            ->join('events', 'tickets.event_id', '=', 'events.id')
            ->where('events.organization_id', $organizationId)
            ->select(
                DB::raw('DATE(tickets.created_at) as date'),
                DB::raw('COUNT(tickets.id) as tickets_count'),
                DB::raw('SUM(events.price) as revenue')
            )
            ->groupBy(DB::raw('DATE(tickets.created_at)'))  // Use the exact same expression
            ->orderBy('date');

        // Modify donations query similarly
        $donationsQuery = DB::table('donations')
            ->join('fundraisings', 'donations.fundraising_id', '=', 'fundraisings.id')
            ->where('fundraisings.organization_id', $organizationId)
            ->select(
                DB::raw('DATE(donations.created_at) as date'),
                DB::raw('COUNT(donations.id) as donations_count'),
                DB::raw('SUM(amount) as amount')
            )
            ->groupBy(DB::raw('DATE(donations.created_at)'))  // Use the exact same expression
            ->orderBy('date');

        // Apply time filter
        if ($timeFilter !== 'all') {
            $date = $this->getDateConstraint($timeFilter);
            $query->where('tickets.created_at', '>=', $date);
            $donationsQuery->where('donations.created_at', '>=', $date);
        }

        return [
            'tickets' => $query->get(),
            'donations' => $donationsQuery->get()
        ];
    }
}