<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseParticipant extends Model
{
    use HasFactory, HasUuids;

    // Bảng không có created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'expense_id',
        'user_id',
        'share_type',
        'share_percent',
        'share_amount',
    ];

    protected $casts = [
        'share_percent' => 'decimal:2',
        'share_amount' => 'decimal:2',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
