<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        return response()->json([
            'data' => [
                'profile' => $profile,
            ],
        ]);
    }
}
