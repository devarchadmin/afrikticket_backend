<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrganizationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || Auth::user()->role !== 'organization') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Organization access required.'
            ], 403);
        }

        return $next($request);
    }
}
