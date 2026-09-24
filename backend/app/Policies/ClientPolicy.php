<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $client->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Client $client): bool
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $client->user_id === $user->id;
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->hasAnyRole(UserRole::Admin, UserRole::Manager);
    }
}
