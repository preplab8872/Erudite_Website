
<?php

use Illuminate\Support\Facades\Route;

use App\Http\Otp\OtpController;
use App\Module\Booking\BookingController;

Route::prefix('booking')->group(function () {

    // Existing OTP controller handles OTP generation.
    Route::post('/otp/send', [
        OtpController::class,
        'send'
    ]);

    // Booking submission and OTP verification.
    Route::post('/confirm', [
        BookingController::class,
        'confirm'
    ]);

});
