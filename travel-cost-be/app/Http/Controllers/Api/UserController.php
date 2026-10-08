<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Requests\User\UpdateUserRequest;

class UserController extends Controller
{
    public function me(Request $request)
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
}
