<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CallSessionController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\NotificationController;
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
    ->middleware('auth:sanctum');

Route::post('/favorites/toggle', [FavoriteController::class, 'toggle'])
    ->middleware('auth:sanctum');

Route::get('/favorites', [FavoriteController::class, 'index'])->middleware('auth:sanctum');
Route::post('/images', [ImageController::class, 'store'])->middleware('auth:sanctum');
