<?php

namespace App\Http\Middleware\Services;

use Illuminate\Support\Facades\DB;

class PhoneValidationService
{
    private const TABLE_NAME = 'crm_example';

    public function checkPhone(string $phone): int
    {
        $phone = trim($phone);

        // Step 1: Validate phone structure.
        if (!preg_match('/^\+?[0-9\s().-]+$/', $phone)) {
            return 0;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return 0;
        }

        // Step 2: Check CRM existence.
        return DB::connection('crm')
            ->table(self::TABLE_NAME)
            ->where('phone_number', $phone)
            ->exists() ? 1 : 0;
    }

    public function checkEmail(string $email): int
    {
        $email = strtolower(trim($email));

        // Step 1: Validate email structure.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }

        // Step 2: Check CRM existence.
        return DB::connection('crm')
            ->table(self::TABLE_NAME)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists() ? 1 : 0;
    }
}