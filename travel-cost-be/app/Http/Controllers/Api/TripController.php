<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\TripMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\DB;

class TripController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $trips = $user->joinedTrips()
            ->with(['owner', 'owner.profile', 'members'])
            ->paginate(10);

        return TripResource::collection($trips);
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $trip = Trip::create([
            'owner_id' => $request->user()->id,
            ...$validated,
        ]);

        TripMember::create([
            'trip_id' => $trip->id,
            'user_id' => $request->user()->id,
            'role' => 'Owner',
        ]);

        return response()->json([
            'message' => 'Trip created successfully.',
            'data' => new TripResource($trip->load(['owner', 'owner.profile', 'members'])),
        ], 201);
    }

    public function show(Request $request, Trip $trip): JsonResponse
    {
        $isMember = $trip->members->contains('user_id', $request->user()->id);

        if (!$isMember) {
            return response()->json([
                'message' => 'You are not authorized to view this trip.',
            ], 403);
        }

        return response()->json([
            'data' => new TripResource($trip->load(['owner', 'owner.profile', 'members'])),
        ]);
    }

    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        if ($trip->owner_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Only the trip owner can update this trip.',
            ], 403);
        }

        $validated = $request->validated();
        $trip->update($validated);

        return response()->json([
            'message' => 'Trip updated successfully.',
            'data' => new TripResource($trip->load(['owner', 'owner.profile', 'members'])),
        ]);
    }

    public function destroy(Request $request, Trip $trip): JsonResponse
    {
        if ($trip->owner_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Only the trip owner can delete this trip.',
            ], 403);
        }

        DB::transaction(function () use ($trip) {
            $lockedTrip = Trip::query()
                ->whereKey($trip->id)
                ->lockForUpdate()
                ->firstOrFail();

            IdempotencyKey::query()
                ->where('trip_id', $lockedTrip->id)
                ->delete();

            Expense::query()
                ->where('trip_id', $lockedTrip->id)
                ->delete();

            ExpenseCategory::query()
                ->where('trip_id', $lockedTrip->id)
                ->delete();

            $lockedTrip->delete();
        });

        return response()->json([
            'message' => 'Trip deleted successfully.',
        ]);
    }
}
