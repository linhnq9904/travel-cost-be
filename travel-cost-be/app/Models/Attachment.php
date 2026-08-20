<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasFactory, HasUuids;

    // Chỉ có uploaded_at
    public $timestamps = false;

    protected $fillable = [
        'expense_id',
        'file_path',
        'file_type',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
