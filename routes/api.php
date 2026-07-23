<?php

use App\Modules\Auction\Infrastructure\Http\Middleware\EnsureIdempotentBidRequest;
use App\Modules\Auction\Presentation\Controllers\AuctionsController;
use App\Modules\Auction\Presentation\Controllers\BidsController;
use App\Modules\Auction\Presentation\Controllers\CategoriesController;
use App\Modules\Auth\Presentation\Controllers\LoginController;
use App\Modules\Auth\Presentation\Controllers\LogoutController;
use App\Modules\Auth\Presentation\Controllers\RegisterController;
use App\Modules\Dashboard\Presentation\Controllers\BusinessDashboardController;
use App\Modules\Dashboard\Presentation\Controllers\TechnicalDashboardController;
use App\Modules\Notification\Presentation\Controllers\NotificationsController;
use App\Modules\User\Presentation\Controllers\ActivityController;
use App\Modules\User\Presentation\Controllers\AvatarController;
use App\Modules\User\Presentation\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterController::class)->middleware('throttle:login');
Route::post('/login', LoginController::class)->middleware('throttle:login');

Route::get('/categories', [CategoriesController::class, 'index']);
Route::get('/auctions', [AuctionsController::class, 'index']);
Route::get('/auctions/{id}', [AuctionsController::class, 'show']);
Route::get('/auctions/{id}/live', [AuctionsController::class, 'live']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', LogoutController::class);

    Route::get('/me', [ProfileController::class, 'show'])->middleware('abilities:profile:read');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('abilities:profile:write');
    Route::post('/profile/avatar', [AvatarController::class, 'store'])->middleware('abilities:profile:write');
    Route::delete('/profile/avatar', [AvatarController::class, 'destroy'])->middleware('abilities:profile:write');

    Route::get('/profile/bids', [ActivityController::class, 'bidHistory'])->middleware('abilities:profile:read');
    Route::get('/profile/auctions/won', [ActivityController::class, 'auctionsWon'])->middleware('abilities:profile:read');
    Route::get('/profile/auctions/lost', [ActivityController::class, 'auctionsLost'])->middleware('abilities:profile:read');
    Route::get('/rankings', [ActivityController::class, 'rankings'])->middleware('abilities:profile:read');

    Route::get('/dashboard/business', [BusinessDashboardController::class, 'show'])->middleware('abilities:dashboard:read');
    Route::get('/dashboard/technical', [TechnicalDashboardController::class, 'show'])->middleware('abilities:dashboard:read');

    Route::middleware('abilities:notifications:read')->group(function (): void {
        Route::get('/notifications', [NotificationsController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationsController::class, 'markAsRead']);
    });

    Route::middleware('abilities:auction:manage')->group(function (): void {
        Route::post('/auctions', [AuctionsController::class, 'store']);
        Route::patch('/auctions/{id}', [AuctionsController::class, 'update']);
        Route::post('/auctions/{id}/activate', [AuctionsController::class, 'activate']);
        Route::post('/auctions/{id}/cancel', [AuctionsController::class, 'cancel']);
    });

    // No PATCH/DELETE for a bid — bids are never edited or cancelled once
    // placed (see ADR-0006).
    Route::post('/auctions/{id}/bids', [BidsController::class, 'store'])
        ->middleware(['abilities:bid:place', 'throttle:bid-placement', EnsureIdempotentBidRequest::class]);
});
