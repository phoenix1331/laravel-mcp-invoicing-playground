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

    // Confirm the typed values actually landed before submitting. On a
    // resource-constrained CI runner, sendKeys can race ahead of the input
    // becoming interactive and silently drop characters - failing fast here
    // with a clear message beats a confusing downstream login failure.
    $browser->assertInputValue('email', $email)
        ->assertInputValue('password', 'password');

    // A generous reload timeout beyond even the raised global default (see
    // DuskTestCase::prepare()): CI's chromedriver degrades further on the
    // second/third login within the same browse() session.
    $browser->waitForReload(fn (Browser $browser) => $browser->press('Log in'), 25)
        ->assertPathIs('/dashboard');
}

test('the invoice UI shows role-appropriate actions for viewer, member, and owner', function () {
    $this->browse(function (Browser $browser) {
        $draftInvoice = Invoice::withoutGlobalScopes()
            ->whereRelation('organisation', 'slug', 'acme')
            ->where('status', InvoiceStatus::Draft)
            ->firstOrFail();

        // Viewer: no "New invoice" button on the list, and no "Team" link in the sidebar.
        loginAsDuskUser($browser, 'user3@email.com');

        $browser->visit('/invoices')
            ->assertSee('Invoices')
            ->assertDontSee('New invoice');

        $browser->visit('/dashboard')
            ->assertDontSee('Team');

        $browser->logout();

        // Member: no "Delete" button on a draft invoice, and no "Team" link in the sidebar.
        loginAsDuskUser($browser, 'user2@email.com');

        $browser->visit('/invoices/'.$draftInvoice->id)
            ->assertSee($draftInvoice->number)
            ->assertDontSee('Delete');

        $browser->visit('/dashboard')
            ->assertDontSee('Team');

        $browser->logout();

        // Owner: sees both "New invoice" and "Delete" on a draft invoice, and sees the "Team" link.
        loginAsDuskUser($browser, 'user1@email.com');

        $browser->visit('/invoices')
            ->assertSee('New invoice');

        $browser->visit('/invoices/'.$draftInvoice->id)
            ->assertSee($draftInvoice->number)
            ->assertSee('Delete');

        $browser->visit('/dashboard')
            ->assertSee('Team');
    });
});
