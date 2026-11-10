<?php

namespace App\Module\Booking;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $connection = 'crm';

    protected $table = 'crm_example';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'city',
    ];

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'update_at';

    public $timestamps = true;
}
