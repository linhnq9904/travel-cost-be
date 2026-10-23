<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'user_id',
        'trip_id',
        'key',
        'request_hash',
        'expense_id',
    ];
}
