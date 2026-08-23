<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can view the organisation's invoices.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the invoice (and download its PDF).
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->organisation_id === $invoice->organisation_id;
    }

    /**
     * Determine whether the user can create draft invoices.
     */
    public function create(User $user): bool
    {
        return $user->role !== UserRole::Viewer;
    }

    /**
     * Determine whether the user can edit the invoice or change its status
     * (send, mark paid, void). Whether the current status allows the specific
     * transition is enforced separately by TransitionInvoiceStatus.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->organisation_id === $invoice->organisation_id
            && $user->role !== UserRole::Viewer;
    }

    /**
     * Determine whether the user can delete the invoice.
     *
     * Only Owners may delete, and only draft invoices are actually removed
     * (DeleteInvoice voids anything else).
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->organisation_id === $invoice->organisation_id
            && $user->role === UserRole::Owner;
    }
}
