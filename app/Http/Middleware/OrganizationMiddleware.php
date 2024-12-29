<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrganizationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'organization') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Organization access required.'
            ], 403);
        }
        return $next($request);
    }
}