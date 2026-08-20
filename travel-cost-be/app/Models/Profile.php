<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    // Bảng profiles không có created_at, chỉ có updated_at
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'full_name',
        'avatar',
        'phone',
        'date_of_birth',
        'gender',
        'address',
        'updated_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
