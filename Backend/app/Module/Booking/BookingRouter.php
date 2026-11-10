<?php

namespace App\Module\Booking;

use App\Http\Middleware\RiskCheckMiddleware;
use App\Http\Otp\OtpController;
use Illuminate\Support\Facades\Route;

class BookingRouter
{
    public static function register(): void
    {
        Route::middleware(['web', RiskCheckMiddleware::class])
            ->prefix('booking')
            ->group(function (): void {

                Route::post('/otp/send', [
                    OtpController::class,
                    'send'
                ])->middleware('throttle:5,1');

                Route::post('/confirm', [
                    BookingController::class,
                    'confirm'
                ])->middleware('throttle:10,1');

            });
    }
}