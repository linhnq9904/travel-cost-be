<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        return new ProfileResource($profile);
    }
    // public function updatePProfile(Request $request)
    // {
    //     $user = $request->user();
    //     $profile = $user->profile;

    //     return response()->json([
    //         'data' => [
    //             'profile' => $profile,
    //         ],
    //     ]);
    // }
}
