<?php

namespace App\Module\Booking;

use App\Http\Controllers\Controller;
use App\Http\Otp\OtpController;
use App\Http\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function confirm(
        Request $request,
        OtpController $otpController,
        OtpService $otpService,
        BookingRepo $bookingRepository
    ): JsonResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:254'],
            'city' => ['required', 'string', 'max:100'],
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+?[0-9\s().-]{7,20}$/'
            ],
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $otpResponse = $otpController->verify(
            new Request([
                'recipient' => $data['email'],
                'otp' => $data['otp'],
            ]),
            $otpService
        );

        if ($otpResponse->getStatusCode() !== 200) {
            return $otpResponse;
        }

        $bookingRepository->queueBooking($data);

        return response()->json([
            'success' => true,
            'message' => 'Booking request received successfully.',
        ], 202);
    }
}