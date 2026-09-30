<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Activity $activity): bool
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $activity->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Activity $activity): bool
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $activity->user_id === $user->id;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $user->hasAnyRole(UserRole::Admin, UserRole::Manager);
    }

    /**
     * Same ownership rule as update() today — named separately (rather than
     * reused directly) so a future change to who can edit an Activity's
     * fields doesn't silently also change who can complete it.
     */
    public function complete(User $user, Activity $activity): bool
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $activity->user_id === $user->id;
    }

    public function reopen(User $user, Activity $activity): bool
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $activity->user_id === $user->id;
    }
}
