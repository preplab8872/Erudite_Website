<?php
namespace App\Http\Otp;

use App\Http\Controllers\Controller;
use App\Http\Otp\OtpService;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SEND OTP
    |--------------------------------------------------------------------------
    */

    public function send(
        Request $request,
        OtpService $otpService
    ): JsonResponse {

        $validated = $request->validate([
            'recipient' => [
                'required',
                'string',
                'email',
                'max:254'
            ]
        ]);

        $result = $otpService->send(
            $validated['recipient']
        );

        return $this->respond($result);
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY OTP
    |--------------------------------------------------------------------------
    */

    public function verify(
        Request $request,
        OtpService $otpService
    ): JsonResponse {

        $validated = $request->validate([
            'recipient' => [
                'required',
                'string',
                'email',
                'max:254'
            ],

            'otp' => [
                'required',
                'string',
                'regex:/^\d{6}$/'
            ]
        ]);

        $result = $otpService->verify(
            $validated['recipient'],
            $validated['otp']
        );

        return $this->respond($result);
    }


    /*
    |--------------------------------------------------------------------------
    | FORMAT RESPONSE
    |--------------------------------------------------------------------------
    */

    private function respond(array $result): JsonResponse
    {
        $httpStatus = $result['_http_status'] ?? 200;

        unset($result['_http_status']);

        return response()->json(
            $result,
            $httpStatus
        );
    }
}
