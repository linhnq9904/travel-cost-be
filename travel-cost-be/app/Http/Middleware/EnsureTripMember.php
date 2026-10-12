<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\TripMember;
use App\Models\Trip;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureTripMember
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tripId = $request->route('tripId');

        if (!is_string($tripId) || !Str::isUuid($tripId)) {
            return response()->json([
                'message' => 'Trip ID không hợp lệ.',
            ], 404);
        }

        $tripExists = Trip::where('id', $tripId)->exists();

        if (!$tripExists) {
            return response()->json([
                'message' => 'Chuyến đi không tồn tại.',
            ], 404);
        }

        $isMember = TripMember::where('trip_id', $tripId)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return response()->json([
                'message' => 'Bạn không phải là thành viên của chuyến đi này.',
            ], 403);
        }

        return $next($request);
    }
}
