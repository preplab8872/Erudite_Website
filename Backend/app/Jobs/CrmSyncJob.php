<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CrmSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public array $bookingData)
    {
    }

    public function handle(): void
    {
        DB::connection('crm')
            ->table('crm_example')
            ->insert([
                'name' => $this->bookingData['name'],
                'email' => $this->bookingData['email'],
                'city' => $this->bookingData['city'],
                'phone' => $this->bookingData['phone'],
                'created_at' => $this->bookingData['created_at'],
                'update_at' => $this->bookingData['update_at'],
            ]);
    }
}
