<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    use HasFactory;
    protected $fillable = ['telegram_id','test_id','correct_answer'];

    public function user()
    {
        return $this->hasOne(User::class,'telegram_id','telegram_id');
    }

    public function test()
    {
        return $this->hasOne(Tests::class,'id','test_id');
    }

    public function testStrlen(): float|int
    {
        if(preg_match('/[0-9]/', $this->test->variant)) {
            return intval(strlen($this->test->variant) / 2);
        }
        return strlen($this->test->variant);
    }
}
