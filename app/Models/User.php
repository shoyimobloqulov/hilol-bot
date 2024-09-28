<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class User extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'step',
        'phone',
        'second_phone',
        'username',
        'certificate',
        'regions',
        'districts',
        'schools',
        'telegram_id'
    ];
}
