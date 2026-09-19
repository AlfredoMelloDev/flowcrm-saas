<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $lead->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $lead->user_id === $user->id;
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasAnyRole(UserRole::Admin, UserRole::Manager);
    }
}
