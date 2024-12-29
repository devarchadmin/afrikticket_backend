<?php

use App\Http\Controllers\EventController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Authcontroller;
use App\Http\Controllers\TicketController;


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'getUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// // Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


// Public event routes
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);

// Admin routes
// Route::middleware(['auth:sanctum', 'admin'])->group(function () {
//     // Admin only routes here
//     Route::get('/admin/users', [AdminController::class, 'getAllUsers']);
//     Route::get('/admin/organizations', [AdminController::class, 'getAllOrganizations']);
//     Route::put('/admin/organizations/{id}/status', [AdminController::class, 'updateOrganizationStatus']);
// });

// Organization routes
Route::middleware(['auth:sanctum', 'organization'])->group(function () {
    // Organization only routes here
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'delete']);
    Route::get('/my-events', [EventController::class, 'organizationEvents']);
    Route::post('/tickets/validate', [TicketController::class, 'validateTicket']);
});


Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/events/{eventId}/tickets', [TicketController::class, 'store']);
});
