<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Organisation;
use App\Models\User;

class OrganisationPolicy
{
    /**
     * Determine whether the user can view the organisation's settings.
     */
    public function view(User $user, Organisation $organisation): bool
    {
        return $user->organisation_id === $organisation->id;
    }

    /**
     * Determine whether the user can update the organisation's settings.
     */
    public function update(User $user, Organisation $organisation): bool
    {
        return $user->organisation_id === $organisation->id
            && $user->role === UserRole::Owner;
    }
}
