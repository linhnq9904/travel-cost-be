<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'owner_id');
    }

    public function tripMemberships(): HasMany
    {
        return $this->hasMany(TripMember::class);
    }

    public function joinedTrips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_members')
            ->withPivot('role', 'joined_at');
    }

    public function expensesPaid(): HasMany
    {
        return $this->hasMany(Expense::class, 'paid_by');
    }

    public function expenseParticipations(): HasMany
    {
        return $this->hasMany(ExpenseParticipant::class);
    }

    public function settlementsSent(): HasMany
    {
        return $this->hasMany(Settlement::class, 'from_user');
    }

    public function settlementsReceived(): HasMany
    {
        return $this->hasMany(Settlement::class, 'to_user');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
