<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminDashboardChartsController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminGeographyController;
use App\Http\Controllers\Api\AdminPropertyController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AdminReviewController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\CallSessionController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\NavbarCountsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ReviewController;
use App\Models\Booking;
use App\Models\Property;
use Illuminate\Support\Facades\Auth;

Route::get('/user', [AuthController::class, 'me'])->middleware('auth:sanctum');
Route::get('/properties/{property}/availability', [PropertyController::class, 'availability']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/navbar-counts', NavbarCountsController::class);

    // API ROUTES FOR POPERTIES :
    Route::get('/properties', [PropertyController::class, 'index']);
    Route::get('/properties/{id}', [PropertyController::class, 'show']);
    Route::post('/properties', [PropertyController::class, 'store']);
    Route::put('/properties/{id}', [PropertyController::class, 'update']);
    Route::delete('/properties/{id}', [PropertyController::class, 'destroy']);


    // API ROUTES FOR BOOKINGS :
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store'])->middleware(('auth:sanctum'));
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);

    // ✅ Accept & Reject
    Route::post('/bookings/{id}/accept',    [BookingController::class, 'accept']);
    Route::post('/bookings/{id}/reject',    [BookingController::class, 'reject']);
    Route::post('/bookings/{id}/cancel',    [BookingController::class, 'cancel']);

    // ✅ حجوزات ملكيات المالك
    Route::get('/owner/bookings', [BookingController::class, 'ownerBookings']);

    // API ROUTES FOR CONVERSATIONS AND MESSAGES : 
    Route::get('/conversations',              [ConversationController::class, 'index']);
    Route::post('/conversations',             [ConversationController::class, 'store']);
    Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markAsRead']);
    Route::get('/conversations/{conversation}/call-sessions/active', [CallSessionController::class, 'active']);
    Route::post('/conversations/{conversation}/call-sessions', [CallSessionController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/call-sessions/incoming', [CallSessionController::class, 'incoming']);
    Route::get('/call-sessions/current', [CallSessionController::class, 'current']);
    Route::post('/call-sessions/{callSession}/accept', [CallSessionController::class, 'accept']);
    Route::post('/call-sessions/{callSession}/decline', [CallSessionController::class, 'decline']);
    Route::post('/call-sessions/{callSession}/end', [CallSessionController::class, 'end']);

    Route::get('/messages/{conversationId}',  [ConversationController::class, 'messages']);
    Route::post('/messages',                  [ConversationController::class, 'sendMessage']);
    Route::post('/reports',                   [ReportController::class, 'store']);
    Route::get('/admin/dashboard/charts',     [AdminDashboardChartsController::class, 'index']);
    Route::get('/admin/dashboard/overview',   [AdminDashboardController::class, 'overview']);
    Route::get('/admin/dashboard/geography',  [AdminGeographyController::class, 'index']);
    Route::get('/admin/users',                [AdminUserController::class, 'index']);
    Route::get('/admin/users/{user}',         [AdminUserController::class, 'show']);
    Route::get('/admin/properties',           [AdminPropertyController::class, 'index']);
    Route::get('/admin/properties/{property}', [AdminPropertyController::class, 'show']);
    Route::get('/admin/reports',              [AdminReportController::class, 'index']);
    Route::get('/admin/reports/{report}',     [AdminReportController::class, 'show']);
    Route::put('/admin/reports/{report}/review', [AdminReportController::class, 'review']);
    Route::put('/admin/reports/{report}/resolve', [AdminReportController::class, 'resolve']);
    Route::put('/admin/reports/{report}/reject', [AdminReportController::class, 'reject']);
    Route::get('/admin/reviews',              [AdminReviewController::class, 'index']);
    Route::get('/admin/reviews/{review}',     [AdminReviewController::class, 'show']);
    Route::put('/admin/reviews/{review}/publish', [AdminReviewController::class, 'publish']);
    Route::put('/admin/reviews/{review}/reject', [AdminReviewController::class, 'reject']);
    // API ROUTES FOR NOTIFICATIONS :

    Route::get('/notifications',          [NotificationController::class, 'index']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);


});

// API FOR AUTHENTICTATION :
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');


Route::post('/reviews', [ReviewController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:5,60']);

Route::post('/favorites/toggle', [FavoriteController::class, 'toggle'])
    ->middleware('auth:sanctum');

Route::get('/favorites', [FavoriteController::class, 'index'])->middleware('auth:sanctum');
Route::post('/images', [ImageController::class, 'store'])->middleware('auth:sanctum');
