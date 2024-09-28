<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMap extends Model
{
    protected $fillable = ['telegram_id','map'];
}
