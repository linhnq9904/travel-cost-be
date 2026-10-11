<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TripMemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('/user', [UserController::class, 'user']);
        Route::get('/user/{id}', [UserController::class, 'show']);
        Route::put('/user', [UserController::class, 'update']);
        Route::patch('/user/{id}', [UserController::class, 'updateById']);
        Route::get('/profile', [ProfileController::class, 'profile']);
        Route::patch('/profile', [ProfileController::class, 'updateProfile']);
        Route::post('/trips/{tripId}/members', [TripMemberController::class, 'store']);
        Route::get('/trips/{tripId}/members', [TripMemberController::class, 'tripMembers']);
    });
});
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('trips', TripController::class);
});
