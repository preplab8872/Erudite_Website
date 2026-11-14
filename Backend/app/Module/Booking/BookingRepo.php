<?php

namespace App\Module\Booking;

use App\Jobs\CrmSyncJob;

class BookingRepo
{
    public function queueBooking(array $data): void
    {
        $timestamp = now()->toDateTimeString();

        CrmSyncJob::dispatch([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'city' => trim($data['city']),
            'phone' => trim($data['phone']),
            'created_at' => $timestamp,
            'update_at' => $timestamp,
        ]);
    }
}
