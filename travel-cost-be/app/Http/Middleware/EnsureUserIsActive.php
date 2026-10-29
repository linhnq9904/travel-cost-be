<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->status !== 'ACTIVE') {
            return response()->json([
                'message' => 'This account has been locked or is inactive.',
            ], 403);
        }

        return $next($request);
    }
}