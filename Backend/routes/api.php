<?php

use App\Http\Middleware\RiskCheckMiddleware;
use App\Http\Otp\OtpController;
use App\Module\Booking\BookingRouter;
use App\Module\FormManager\FormmanagerRouter;
use Illuminate\Support\Facades\Route;
use App\Module\ResourceManager\ResourceManagerRouter;


Route::middleware([
    'web',
    RiskCheckMiddleware::class,
    'throttle:10,1',
])->prefix('otp')->group(function (): void {

    Route::post('/send', [OtpController::class, 'send']);

    Route::post('/verify', [OtpController::class, 'verify']);

});

BookingRouter::register();

FormmanagerRouter::register();

ResourceManagerRouter::register();