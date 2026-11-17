<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ReservationController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return new UserResource($request->user());
})->middleware('auth:sanctum');

/* Auth Routes */
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me/reservations', [ReservationController::class, 'mine']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy']);
});

/* Event Routes */
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);
Route::get('/events/{event}/reservations', [EventController::class, 'reservations']);
