<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view the organisation's customers.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->organisation_id === $customer->organisation_id;
    }

    /**
     * Determine whether the user can create customers.
     */
    public function create(User $user): bool
    {
        return $user->role !== UserRole::Viewer;
    }

    /**
     * Determine whether the user can update the customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->organisation_id === $customer->organisation_id
            && $user->role !== UserRole::Viewer;
    }

    /**
     * Determine whether the user can delete the customer.
     *
     * Only permitted if the customer has no invoices, per brief §7.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->organisation_id === $customer->organisation_id
            && $user->role === UserRole::Owner
            && $customer->invoices()->doesntExist();
    }
}
