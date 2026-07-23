<?php

use App\Modules\Auction\Presentation\Controllers\AuctionsController;
use App\Modules\Auction\Presentation\Controllers\CategoriesController;
use App\Modules\Auth\Presentation\Controllers\LoginController;
use App\Modules\Auth\Presentation\Controllers\LogoutController;
use App\Modules\Auth\Presentation\Controllers\RegisterController;
use App\Modules\User\Presentation\Controllers\ActivityStubController;
use App\Modules\User\Presentation\Controllers\AvatarController;
use App\Modules\User\Presentation\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterController::class)->middleware('throttle:login');
Route::post('/login', LoginController::class)->middleware('throttle:login');

Route::get('/categories', [CategoriesController::class, 'index']);
Route::get('/auctions', [AuctionsController::class, 'index']);
Route::get('/auctions/{id}', [AuctionsController::class, 'show']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', LogoutController::class);

    Route::get('/me', [ProfileController::class, 'show'])->middleware('abilities:profile:read');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('abilities:profile:write');
    Route::post('/profile/avatar', [AvatarController::class, 'store'])->middleware('abilities:profile:write');
    Route::delete('/profile/avatar', [AvatarController::class, 'destroy'])->middleware('abilities:profile:write');

    Route::get('/profile/bids', [ActivityStubController::class, 'bidHistory'])->middleware('abilities:profile:read');
    Route::get('/profile/auctions/won', [ActivityStubController::class, 'auctionsWon'])->middleware('abilities:profile:read');
    Route::get('/profile/auctions/lost', [ActivityStubController::class, 'auctionsLost'])->middleware('abilities:profile:read');
    Route::get('/rankings', [ActivityStubController::class, 'rankings'])->middleware('abilities:profile:read');

    Route::middleware('abilities:auction:manage')->group(function (): void {
        Route::post('/auctions', [AuctionsController::class, 'store']);
        Route::patch('/auctions/{id}', [AuctionsController::class, 'update']);
        Route::post('/auctions/{id}/activate', [AuctionsController::class, 'activate']);
        Route::post('/auctions/{id}/cancel', [AuctionsController::class, 'cancel']);
    });
});
