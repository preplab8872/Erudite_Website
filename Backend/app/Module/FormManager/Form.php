<?php

namespace App\Module\FormManager;

use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    protected $connection = 'crm';

    protected $table = 'forms';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'section',
        'forms',
        'form_name',
    ];
}
