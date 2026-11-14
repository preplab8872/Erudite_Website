<?php
namespace App\Http\Otp;

use App\Http\Middleware\Services\PhoneValidationService;
use Carbon\Carbon;
use Throwable;
use RuntimeException;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

use Illuminate\Contracts\Cache\LockTimeoutException;

class OtpService
{
    public function __construct(
        private readonly PhoneValidationService $phoneValidationService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | SEND OTP
    |--------------------------------------------------------------------------
    */

    public function send(string $recipient): array
    {
        $recipient = strtolower(trim($recipient));

        // SMTP implementation below is email-based.
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return $this->response(
                false,
                'Invalid email address.',
                422,
                ['otp_sender' => 1]
            );
        }

        // Check whether recipient exists.
        if (!$this->recipientExists($recipient)) {
            return $this->response(
                false,
                'Recipient could not be verified.',
                422,
                ['otp_sender' => 1]
            );
        }

        $cache = Cache::store(
            config('otp.cache_store', 'file')
        );

        [$cacheKey, $lockKey] = $this->getKeys($recipient);

        try {

            return $cache->lock($lockKey, 60)->block(
                10,
                function () use ($cache, $cacheKey, $recipient) {

                    // Read and update the logical switch.
                    $previousState = $this->refreshState(
                        $cache,
                        $cacheKey
                    );

                    /*
                     * Main controlling switch.
                     *
                     * 1 = Sending allowed
                     * 0 = Sending blocked
                     */

                    if ($previousState['otp_sender'] !== 1) {

                        $elapsed = $this->getElapsedTime(
                            $previousState
                        );

                        $remaining = max(
                            1,
                            config('otp.send_cooldown_seconds') - $elapsed
                        );

                        return $this->response(
                            false,
                            'Please wait before requesting another OTP.',
                            429,
                            [
                                'otp_sender' => 0,
                                'retry_after_seconds' => $remaining
                            ]
                        );
                    }

                    // Generate a six-digit OTP.
                    $otp = str_pad(
                        (string) random_int(0, 999999),
                        config('otp.otp_length', 6),
                        '0',
                        STR_PAD_LEFT
                    );

                    $now = now('UTC');

                    // New cache state.
                    $newState = [
                        'otp_sender' => 0,

                        'otp_hash' => $this->createOtpHash(
                            $recipient,
                            $otp
                        ),

                        'last_sent_at' => $now->toIso8601String(),

                        'attempts' => 0,
                    ];

                    /*
                     * Store the new state before delivery.
                     * This replaces the previous OTP hash.
                     */

                    $this->saveState(
                        $cache,
                        $cacheKey,
                        $newState
                    );

                    try {

                        // Deliver OTP through SMTP.
                        Mail::raw(
                            "Your verification code is: {$otp}\n\n"
                            . "This code expires in 3 minutes.\n"
                            . "Please do not share this code with anyone.",

                            function ($message) use ($recipient) {

                                $message
                                    ->to($recipient)
                                    ->subject(
                                        config('otp.mail_subject')
                                    );

                            }
                        );

                    } catch (Throwable $e) {

                        /*
                         * If delivery fails, restore the previous state.
                         * An older OTP, if still valid, remains usable.
                         */

                        $this->saveState(
                            $cache,
                            $cacheKey,
                            $previousState
                        );

                        Log::error('OTP delivery failed.', [
                            'recipient_hash' => hash(
                                'sha256',
                                $recipient
                            ),
                            'exception' => $e->getMessage()
                        ]);

                        return $this->response(
                            false,
                            'OTP delivery failed. Please try again.',
                            503,
                            [
                                'otp_sender' =>
                                    $previousState['otp_sender']
                            ]
                        );
                    }

                    return $this->response(
                        true,
                        'OTP sent successfully.',
                        200,
                        [
                            'otp_sender' => 0,
                            'expires_in_seconds' =>
                                config('otp.otp_ttl_seconds'),
                            'send_cooldown_seconds' =>
                                config('otp.send_cooldown_seconds')
                        ]
                    );

                }
            );

        } catch (LockTimeoutException $e) {

            return $this->response(
                false,
                'Another request is being processed. Try again.',
                429,
                ['otp_sender' => 0]
            );

        }
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY OTP
    |--------------------------------------------------------------------------
    */

    public function verify(
        string $recipient,
        string $submittedOtp
    ): array {

        $recipient = strtolower(trim($recipient));

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return $this->response(
                false,
                'Invalid recipient.',
                422
            );
        }

        if (!preg_match('/^\d{6}$/', $submittedOtp)) {
            return $this->response(
                false,
                'OTP must contain exactly six digits.',
                422
            );
        }

        $cache = Cache::store(
            config('otp.cache_store', 'file')
        );

        [$cacheKey, $lockKey] = $this->getKeys($recipient);

        try {

            return $cache->lock($lockKey, 60)->block(
                10,
                function () use (
                    $cache,
                    $cacheKey,
                    $recipient,
                    $submittedOtp
                ) {

                    $state = $this->refreshState(
                        $cache,
                        $cacheKey
                    );

                    /*
                     * No active OTP exists.
                     */

                    if ($state['otp_hash'] === null) {

                        return $this->response(
                            false,
                            'OTP has expired or is no longer available.',
                            422,
                            [
                                'otp_sender' => $state['otp_sender']
                            ]
                        );
                    }

                    /*
                     * Maximum attempts reached.
                     */

                    if (
                        $state['attempts'] >=
                        config('otp.max_attempts')
                    ) {

                        $state['otp_hash'] = null;

                        $this->saveState(
                            $cache,
                            $cacheKey,
                            $state
                        );

                        return $this->response(
                            false,
                            'Maximum verification attempts reached.',
                            429,
                            [
                                'otp_sender' => $state['otp_sender']
                            ]
                        );
                    }

                    // Generate candidate hash.
                    $submittedHash = $this->createOtpHash(
                        $recipient,
                        $submittedOtp
                    );

                    /*
                     * Compare submitted OTP with stored hash.
                     */

                    if (hash_equals(
                        $state['otp_hash'],
                        $submittedHash
                    )) {

                        // Successful verification.
                        $state['otp_hash'] = null;
                        $state['attempts'] = 0;

                        /*
                         * Do not clear last_sent_at.
                         * This preserves the 40-second cooldown.
                         */

                        $this->saveState(
                            $cache,
                            $cacheKey,
                            $state
                        );

                        return $this->response(
                            true,
                            'OTP verified successfully.',
                            200,
                            [
                                'otp_sender' => $state['otp_sender']
                            ]
                        );
                    }

                    /*
                     * Incorrect OTP.
                     */

                    $state['attempts']++;

                    $remainingAttempts = max(
                        0,
                        config('otp.max_attempts') - $state['attempts']
                    );

                    $attemptsExceeded =
                        $state['attempts'] >=
                        config('otp.max_attempts');

                    if ($attemptsExceeded) {
                        $state['otp_hash'] = null;
                    }

                    $this->saveState(
                        $cache,
                        $cacheKey,
                        $state
                    );

                    return $this->response(
                        false,
                        $attemptsExceeded
                            ? 'Maximum verification attempts reached.'
                            : 'Incorrect OTP.',

                        $attemptsExceeded ? 429 : 422,

                        [
                            'otp_sender' => $state['otp_sender'],
                            'attempts_remaining' => $remainingAttempts
                        ]
                    );

                }
            );

        } catch (LockTimeoutException $e) {

            return $this->response(
                false,
                'Another request is being processed. Try again.',
                429
            );

        }
    }


    /*
    |--------------------------------------------------------------------------
    | RECIPIENT VALIDATION
    |--------------------------------------------------------------------------
    */

    private function recipientExists(string $recipient): bool
    {
        /*
         * Connect this call to your existing
         * PhoneValidationService method.
         *
         * Expected return:
         * 1 = Recipient exists
         * 0 = Recipient does not exist
         */

        return (int) $this->phoneValidationService
            ->checkEmail($recipient) === 1;
    }


    /*
    |--------------------------------------------------------------------------
    | OTP HASH
    |--------------------------------------------------------------------------
    */

    private function createOtpHash(
        string $recipient,
        string $otp
    ): string {

        $secret = (string) config('otp.hash_key');

        if ($secret === '') {
            throw new RuntimeException(
                'OTP_HASH_KEY is not configured.'
            );
        }

        return hash_hmac(
            'sha256',
            $recipient . '|' . $otp,
            $secret
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CACHE KEYS
    |--------------------------------------------------------------------------
    */

    private function getKeys(string $recipient): array
    {
        $appKey = (string) config('app.key');

        $identifier = hash_hmac(
            'sha256',
            $recipient,
            $appKey
        );

        return [
            'otp:' . $identifier,
            'otp-lock:' . $identifier
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | DEFAULT STATE
    |--------------------------------------------------------------------------
    */

    private function emptyState(): array
    {
        return [
            'otp_sender' => 1,
            'otp_hash' => null,
            'last_sent_at' => null,
            'attempts' => 0
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | REFRESH STATE USING SERVER TIME
    |--------------------------------------------------------------------------
    */

    private function refreshState(
        $cache,
        string $cacheKey
    ): array {

        $state = $cache->get($cacheKey);

        // No previous OTP state.
        if (!is_array($state)) {
            return $this->emptyState();
        }

        if (empty($state['last_sent_at'])) {
            $cache->forget($cacheKey);

            return $this->emptyState();
        }

        try {

            $lastSentAt = Carbon::parse(
                $state['last_sent_at']
            )->utc();

        } catch (Throwable $e) {

            $cache->forget($cacheKey);

            return $this->emptyState();
        }

        $now = now('UTC');

        $elapsed = max(
            0,
            $now->timestamp - $lastSentAt->timestamp
        );

        /*
         * 180-second expiry cycle.
         */

        if (
            $elapsed >=
            config('otp.otp_ttl_seconds')
        ) {

            $cache->forget($cacheKey);

            return $this->emptyState();
        }

        /*
         * 40-second send cycle.
         */

        $state['otp_sender'] =
            $elapsed >= config('otp.send_cooldown_seconds')
                ? 1
                : 0;

        $state['otp_hash'] = $state['otp_hash'] ?? null;

        $state['attempts'] = max(
            0,
            (int) ($state['attempts'] ?? 0)
        );

        // Preserve the original expiration deadline.
        $this->saveState(
            $cache,
            $cacheKey,
            $state
        );

        return $state;
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE CACHE WITHOUT EXTENDING THE OTP LIFETIME
    |--------------------------------------------------------------------------
    */

    private function saveState(
        $cache,
        string $cacheKey,
        array $state
    ): void {

        if (empty($state['last_sent_at'])) {
            $cache->forget($cacheKey);
            return;
        }

        $lastSentAt = Carbon::parse(
            $state['last_sent_at']
        )->utc();

        $expiresAt = $lastSentAt->copy()->addSeconds(
            config('otp.otp_ttl_seconds')
        );

        if ($expiresAt->lessThanOrEqualTo(now('UTC'))) {
            $cache->forget($cacheKey);
            return;
        }

        $cache->put(
            $cacheKey,
            $state,
            $expiresAt
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ELAPSED TIME
    |--------------------------------------------------------------------------
    */

    private function getElapsedTime(array $state): int
    {
        if (empty($state['last_sent_at'])) {
            return 0;
        }

        $lastSentAt = Carbon::parse(
            $state['last_sent_at']
        )->utc();

        return max(
            0,
            now('UTC')->timestamp - $lastSentAt->timestamp
        );
    }


    /*
    |--------------------------------------------------------------------------
    | STANDARD RESPONSE
    |--------------------------------------------------------------------------
    */

    private function response(
        bool $success,
        string $message,
        int $httpStatus,
        array $extra = []
    ): array {

        return array_merge(
            [
                'success' => $success,
                'message' => $message,
                '_http_status' => $httpStatus
            ],
            $extra
        );
    }
}
