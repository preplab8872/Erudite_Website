<?php

return [

    // Time allowed between OTP sends
    'send_cooldown_seconds' => 40,

    // OTP validity
    'otp_ttl_seconds' => 180,

    // Maximum incorrect verification attempts
    'max_attempts' => 5,

    // OTP configuration
    'otp_length' => 6,

    // Secret used to generate OTP HMAC hashes
    'hash_key' => env('OTP_HASH_KEY', env('APP_KEY')),

    // Cache configuration
    'cache_store' => 'file',

    // Email configuration
    'mail_subject' => 'Your Verification Code',

];