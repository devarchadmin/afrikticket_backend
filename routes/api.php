<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FundraisingController;
use App\Http\Controllers\OrganizationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TicketController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\OrganizationMiddleware;



//testing route that return a message
Route::get('/hello-testing', function () {
    return response()->json([
        'message' => 'Hello from AfrikTicket API!'
    ]);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'getAuthUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/events/calendar', [EventController::class, 'getCalendarEvents']);
});


// // Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


// Public event routes
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);

// Organization routes
Route::middleware(['auth:sanctum', OrganizationMiddleware::class])->group(function () {

    // Organization only routes here
    Route::post('/events', [EventController::class, 'store']);
    Route::put('', [EventController::class, 'update']); // Changed path
    Route::delete('/org/events/{id}', [EventController::class, 'delete']); // Changed path
    Route::get('/org/events', [EventController::class, 'organizationEvents']);

    Route::post('/tickets/validate', [TicketController::class, 'validateTicket']);
    //fundraising routes
    Route::post('/fundraising', [FundraisingController::class, 'store']);
    Route::put('/org/fundraising/{id}', [FundraisingController::class, 'update']); // Changed path
    Route::delete('/org/fundraising/{id}', [FundraisingController::class, 'delete']); // Changed path
    Route::get('/org/fundraisings', [FundraisingController::class, 'organizationFundraisings']);

    //organization dashboard
    Route::get('/organization/dashboard', [OrganizationController::class, 'getDashboardStats']);

    //update organization profile
    Route::put('/organization/update', [OrganizationController::class, 'updateOrganization']);
});

// Admin routes
Route::middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
    Route::get('/admin/dashboard/stats', [AdminController::class, 'getDashboardStats']);
    Route::get('/admin/users', [AdminController::class, 'getAllUsers']);
    Route::get('/admin/organizations', [AdminController::class, 'getAllOrganizations']);

    // Pending content
    Route::get('/admin/pending', [AdminController::class, 'getPendingContent']);
    Route::get('/admin/pending/events', [AdminController::class, 'getPendingEvents']);
    Route::get('/admin/pending/fundraisings', [AdminController::class, 'getPendingFundraisings']);
    Route::get('/admin/pending/orgs', [AdminController::class, 'getPendingOrganizations']);

    // Review content
    Route::put('/admin/events/{id}/review', [AdminController::class, 'reviewEvent']);
    Route::put('/admin/fundraisings/{id}/review', [AdminController::class, 'reviewFundraising']);
    Route::put('/admin/organizations/{id}/status', [AdminController::class, 'updateOrganizationStatus']);

    Route::delete('/admin/organizations/{id}', [AdminController::class, 'deleteOrganisation']);


    //admin management routes 
    Route::post('/admin/create', [AdminController::class, 'createAdmin']);
    Route::put('/admin/{id}/role', [AdminController::class, 'updateAdminRole']);
    Route::put('/admin/events/{id}', [EventController::class, 'update']);
    Route::delete('/admin/events/{id}', [EventController::class, 'delete']);
    Route::put('/admin/fundraising/{id}', [FundraisingController::class, 'update']);
    Route::delete('/admin/fundraising/{id}', [FundraisingController::class, 'delete']);
});

Route::get('/fundraising', [FundraisingController::class, 'index']);
Route::get('/fundraising/{id}', [FundraisingController::class, 'show']);



Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/events/{eventId}/tickets', [TicketController::class, 'store']);

    Route::post('/fundraising/{fundraisingId}/donate', [DonationController::class, 'store']);
    Route::get('/user/donations', [DonationController::class, 'myDonations']);

    Route::get('/user/tickets', [TicketController::class, 'myTicket']);
    Route::get('/user/events', [EventController::class, 'userEvents']);
    
    Route::get('/user/fundraisings', [FundraisingController::class, 'userFundraisings']);

    // User routes 
    Route::get('/user/{id}', [AuthController::class, 'getUser']);
    Route::put('/user/{id}', [AuthController::class, 'updateUser']);
    Route::put('/user/{id}/password', [AuthController::class, 'updateUserPassword']);


    //update password and update profile   
    Route::put('/user/{id}/password', [AuthController::class, 'updateUserPassword']);
    Route::put('/user/updateProfile', [EventController::class, 'updateUser']);
    Route::get('/user/profile', [AuthController::class, 'getAuthUser']);


});
