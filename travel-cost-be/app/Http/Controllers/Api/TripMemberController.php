<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TripMember;
use App\Models\Trip;
use App\Http\Requests\TripMember\StoreTripMemberRequest;
use App\Http\Requests\TripMember\UpdateTripMemberRequest;
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

    public function destroy(Request $request, $tripId, $userId)
    {
        $trip = Trip::findOrFail($tripId);
        $member = TripMember::where('trip_id', $trip->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Check if the authenticated user is the owner of the trip or the member themselves
        if ($request->user()->id !== $trip->owner_id && $request->user()->id !== $member->user_id) {
            return response()->json(['message' => 'Bạn không có quyền xóa thành viên này'], 403);
        }

        $member->delete();

        return response()->json(['message' => 'Xóa thành viên thành công'], 200);
    }

    public function update(UpdateTripMemberRequest $request, $tripId, $userId)
    {
        $trip = Trip::findOrFail($tripId);
        $member = TripMember::where('trip_id', $trip->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Check if the authenticated user is the owner of the trip
        if ($request->user()->id !== $trip->owner_id) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật thành viên này'], 403);
        }

        $validated = $request->validated();

        $member->update($validated);

        return response()->json([
            'message' => 'Cập nhật vai trò thành viên thành công',
            'data' => $member,
        ], 200);
    }
}
