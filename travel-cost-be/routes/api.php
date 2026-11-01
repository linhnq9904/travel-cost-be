<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\TripMemberController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\TripBalanceController;
use App\Http\Middleware\EnsureTripMember;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureUserIsActive;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me'])->middleware(EnsureUserIsActive::class);
    });
});

Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function () {
    Route::get('user', [UserController::class, 'user']);
    Route::get('user/{id}', [UserController::class, 'show']);
    Route::put('user', [UserController::class, 'update']);
    Route::patch('user/{id}', [UserController::class, 'updateById']);

    Route::get('profile', [ProfileController::class, 'profile']);
    Route::patch('profile', [ProfileController::class, 'updateProfile']);

    Route::apiResource('trips', TripController::class);

    Route::prefix('trips/{tripId}')
        ->middleware(EnsureTripMember::class)
        ->group(function () {
            Route::post('/members', [TripMemberController::class, 'store']);
            Route::get('/members', [TripMemberController::class, 'tripMembers']);
            Route::delete('/members/{userId}', [TripMemberController::class, 'destroy']);
            Route::patch('/members/{userId}', [TripMemberController::class, 'update']);

            Route::post('/expense-categories', [ExpenseCategoryController::class, 'store']);
            Route::get('/expense-categories', [ExpenseCategoryController::class, 'index']);
            Route::patch('/expense-categories/{categoryId}', [ExpenseCategoryController::class, 'update']);
            Route::delete('/expense-categories/{categoryId}', [ExpenseCategoryController::class, 'destroy']);

            Route::post('/expenses', [ExpenseController::class, 'store']);
            Route::get('/expenses', [ExpenseController::class, 'index']);
            Route::get('/expenses/{expenseId}', [ExpenseController::class, 'show']);
            Route::patch('/expenses/{expenseId}', [ExpenseController::class, 'update']);
            Route::delete('/expenses/{expenseId}', [ExpenseController::class, 'destroy']);
            Route::put('/expenses/{expenseId}/participants', [ExpenseController::class, 'updateParticipants']);

            Route::get('/balances', [TripBalanceController::class, 'index']);
        });
});
