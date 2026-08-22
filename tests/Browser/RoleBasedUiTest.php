<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Laravel\Dusk\Browser;

function loginAsDuskUser(Browser $browser, string $email): void
{
    $browser->visit('/login')
        ->waitFor('input[name="email"]', 10)
        ->type('email', $email)
        ->type('password', 'password');

    $browser->waitForReload(fn (Browser $browser) => $browser->press('Log in'))
        ->assertPathIs('/dashboard');
}

test('the invoice UI shows role-appropriate actions for viewer, member, and owner', function () {
    $this->browse(function (Browser $browser) {
        $draftInvoice = Invoice::withoutGlobalScopes()
            ->whereRelation('organisation', 'slug', 'acme')
            ->where('status', InvoiceStatus::Draft)
            ->firstOrFail();

        // Viewer: no "New invoice" button on the list.
        loginAsDuskUser($browser, 'user3@email.com');

        $browser->visit('/invoices')
            ->assertSee('Invoices')
            ->assertDontSee('New invoice');

        $browser->logout();

        // Member: no "Delete" button on a draft invoice.
        loginAsDuskUser($browser, 'user2@email.com');

        $browser->visit('/invoices/'.$draftInvoice->id)
            ->assertSee($draftInvoice->number)
            ->assertDontSee('Delete');

        $browser->logout();

        // Owner: sees both "New invoice" and "Delete" on a draft invoice.
        loginAsDuskUser($browser, 'user1@email.com');

        $browser->visit('/invoices')
            ->assertSee('New invoice');

        $browser->visit('/invoices/'.$draftInvoice->id)
            ->assertSee($draftInvoice->number)
            ->assertSee('Delete');
    });
});
