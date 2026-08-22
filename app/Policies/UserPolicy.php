<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view the organisation's team list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific team member.
     */
    public function view(User $user, User $model): bool
    {
        return $user->organisation_id === $model->organisation_id;
    }

    /**
     * Determine whether the user can invite a new team member.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Owner;
    }

    /**
     * Determine whether the user can change a team member's role.
     */
    public function update(User $user, User $model): bool
    {
        return $user->organisation_id === $model->organisation_id
            && $user->role === UserRole::Owner;
    }
}
