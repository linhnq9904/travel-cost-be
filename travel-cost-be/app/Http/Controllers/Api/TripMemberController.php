<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TripMember;
use App\Models\Trip;
use App\Http\Requests\TripMember\StoreTripMemberRequest;
use Illuminate\Http\Request;

class TripMemberController extends Controller
{
    public function store(StoreTripMemberRequest $request)
    {
        $validated = $request->validated();
        $tripId = $request->route('tripId');

        $exists = TripMember::where('trip_id', $tripId)
            ->Where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Người dùng đã là thành viên của chuyến đi này'], 409);
        }

        $member = TripMember::create([
            'trip_id' => $tripId,
            'user_id' => $validated['user_id'],
            'role' => $validated['role'] ?? 'member',
            'joined_at' => now(),
        ]);

        return response()->json([
            'message' => 'Thêm thành viên thành công',
            'data' => $member,
        ], 201);

    }
    public function tripMembers($tripId)
    {
        $trip = Trip::findOrFail($tripId);

        $members = TripMember::where('trip_id', $trip->id)->with('user')->get();

        return response()->json([
            'data' => $members,
        ], 200);
    }

}
