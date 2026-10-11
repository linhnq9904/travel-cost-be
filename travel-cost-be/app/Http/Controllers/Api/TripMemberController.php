<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TripMember;
use App\Models\Trip;
use App\Http\Requests\TripMember\StoreTripMemberRequest;
use App\Http\Requests\TripMember\UpdateTripMemberRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;

class TripMemberController extends Controller
{
    public function store(StoreTripMemberRequest $request)
    {

        $validated = $request->validated();
        $tripId = $request->route('tripId');
        $trip = Trip::findOrFail($tripId);
        Gate::authorize('create', [TripMember::class, $trip]);

        $exists = TripMember::where('trip_id', $tripId)
            ->Where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Người dùng đã là thành viên của chuyến đi này'], 409);
        }

        $member = TripMember::create([
            'trip_id' => $tripId,
            'user_id' => $validated['user_id'],
            'role' => $validated['role'] ?? 'Member',
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
        Gate::authorize('viewAny', [TripMember::class, $trip]);
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
        Gate::authorize('delete', $member);

        $member->delete();

        return response()->json(['message' => 'Xóa thành viên thành công'], 200);
    }

    public function update(UpdateTripMemberRequest $request, $tripId, $userId)
    {
        $trip = Trip::findOrFail($tripId);
        $member = TripMember::where('trip_id', $trip->id)
            ->where('user_id', $userId)
            ->firstOrFail();
        Gate::authorize('update', $member);

        $validated = $request->validated();

        $member->update($validated);

        return response()->json([
            'message' => 'Cập nhật vai trò thành viên thành công',
            'data' => $member,
        ], 200);
    }
}
