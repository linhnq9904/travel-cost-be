<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\TripMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class UserController extends Controller
{
    private function persistAccountChanges(
        UpdateUserRequest $request,
        User $user
    ): User {
        $data = $request->validated();

        unset($data['current_password']);

        $changingPassword = array_key_exists('password', $data);

        if ($changingPassword) {
            $data['password'] = Hash::make($data['password']);
        }

        $currentToken = $request->user()->currentAccessToken();

        DB::transaction(function () use ($user, $data, $changingPassword, $currentToken) {
            $user->update($data);

            if ($changingPassword) {
                $tokens = $user->tokens();

                if ($currentToken instanceof PersonalAccessToken) {
                    $tokens->where('id', '!=', $currentToken->getKey());
                }

                $tokens->delete();
            }
        });

        return $user->fresh();
    }
    public function user(Request $request)
    {
        $user = $request->user();

        return new UserResource($user);
    }
    public function update(UpdateUserRequest $request)
    {
        $user = $this->persistAccountChanges(
            $request,
            $request->user()
        );

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

        if ($request->user()->id !== $user->id) {
            abort(403, 'You are not authorized to update this account.');
        }

        $user = $this->persistAccountChanges($request, $user);

        return new UserResource($user);
    }
}
