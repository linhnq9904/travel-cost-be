<?php

namespace App\Policies;

use App\Models\TripMember;
use App\Models\User;
use App\Models\Trip;
use Illuminate\Auth\Access\Response;

class TripMemberPolicy
{
    /**
     * Determine whether the user can view any models.
     */

    private function isOwner(User $user, Trip $trip): bool
    {
        return $user->id === $trip->owner_id;
    }

    private function isAdmin(User $user, Trip $trip): bool
    {
        return $trip->members()
            ->where('user_id', $user->id)
            ->where('role', 'Admin')
            ->exists();
    }



    public function viewAny(User $user, Trip $trip): bool
    {
        return $this->isOwner($user, $trip)
            || $this->isAdmin($user, $trip)
            || $trip->members()
                ->where('user_id', $user->id)
                ->exists();
    }



    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TripMember $tripMember): bool
    {

        return $this->viewAny($user, $tripMember->trip);

    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Trip $trip): bool
    {
        return $this->isOwner($user, $trip)
            || $this->isAdmin($user, $trip);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TripMember $tripMember): bool
    {
        $trip = $tripMember->trip;

        if ($user->id === $tripMember->user_id) {
            return false;
        }

        if ($this->isOwner($user, $trip)) {
            return $tripMember->role !== 'Owner';
        }

        if ($this->isAdmin($user, $trip)) {
            return $tripMember->role === 'Member';
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TripMember $tripMember): bool
    {
        $trip = $tripMember->trip;

        if ($tripMember->role === 'Owner') {
            return false;
        }

        if ($user->id === $tripMember->user_id) {
            return true;
        }

        if ($this->isOwner($user, $trip)) {
            return true;
        }

        if ($this->isAdmin($user, $trip)) {
            return $tripMember->role === 'Member';
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TripMember $tripMember): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TripMember $tripMember): bool
    {
        return false;
    }
}
