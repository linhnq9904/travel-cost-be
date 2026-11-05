<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\TripMember;

class UserController extends Controller
{
    public function user(Request $request)
    {
        $user = $request->user();

        return new UserResource($user);
    }
    public function update(UpdateUserRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();
        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }
        $user->update($validated);
        return new UserResource($user);
    }
    public function show(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $authUser = $request->user();

        if ($authUser->id !== $user->id) {
            $sharesTrip = TripMember::query()
                ->where('user_id', $user->id)
                ->whereIn(
                    'trip_id',
                    TripMember::query()
                        ->select('trip_id')
                        ->where('user_id', $authUser->id)
                )
                ->exists();

            if (!$sharesTrip) {
                abort(404, 'User not found.');
            }
        }

        return new UserResource($user);
    }
    public function updateById(UpdateUserRequest $request, $id)
    {
        $user = User::findOrFail($id);
        $authUser = $request->user();
        if ($authUser->id !== $user->id) {
            abort(403, 'Bạn không có quyền cập nhật tài khoản này.');
        }
        $validated = $request->validated();
        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }
        $user->update($validated);
        return new UserResource($user->fresh());
    }
}
