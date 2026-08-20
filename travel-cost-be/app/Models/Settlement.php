<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    use HasFactory, HasUuids;

    // Chỉ có created_at, không có updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'trip_id',
        'from_user',
        'to_user',
        'amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user');
    }
}
