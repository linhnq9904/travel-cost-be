<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\TripMemberController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Middleware\EnsureTripMember;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [UserController::class, 'user']);
    Route::get('user/{id}', [UserController::class, 'show']);
    Route::put('user', [UserController::class, 'update']);
    Route::patch('user/{id}', [UserController::class, 'updateById']);

    Route::get('profile', [ProfileController::class, 'profile']);
    Route::patch('profile', [ProfileController::class, 'updateProfile']);

    Route::apiResource('trips', TripController::class);

    Route::middleware(EnsureTripMember::class)->group(function () {
        Route::post('trips/{tripId}/members', [TripMemberController::class, 'store']);
        Route::get('trips/{tripId}/members', [TripMemberController::class, 'tripMembers']);
        Route::delete('trips/{tripId}/members/{userId}', [TripMemberController::class, 'destroy']);
        Route::patch('trips/{tripId}/members/{userId}', [TripMemberController::class, 'update']);

        Route::post('trips/{tripId}/expense-categories', [ExpenseCategoryController::class, 'store']);
        Route::get('trips/{tripId}/expense-categories', [ExpenseCategoryController::class, 'index']);
        Route::patch('trips/{tripId}/expense-categories/{categoryId}', [ExpenseCategoryController::class, 'update']);
        Route::delete('trips/{tripId}/expense-categories/{categoryId}', [ExpenseCategoryController::class, 'destroy']);

        Route::post('trips/{tripId}/expenses', [ExpenseController::class, 'store']);
        Route::get('trips/{tripId}/expenses', [ExpenseController::class, 'index']);
        Route::get('trips/{tripId}/expenses/{expenseId}', [ExpenseController::class, 'show']);
    });
});
