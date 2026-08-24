<?php

declare(strict_types=1);

use Laravel\Dusk\Browser;

test('a member creates an invoice, sends it, marks it paid, and it becomes immutable', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->waitFor('input[name="email"]', 10)
            ->type('email', 'user2@email.com')
            ->type('password', 'password');

        // Confirm the typed values actually landed before submitting - see
        // the equivalent assertion in RoleBasedUiTest's loginAsDuskUser().
        $browser->assertInputValue('email', 'user2@email.com')
            ->assertInputValue('password', 'password');

        $browser->waitForReload(fn (Browser $browser) => $browser->press('Log in'), 25)
            ->assertPathIs('/dashboard');

        $browser->visit('/invoices/create')
            ->assertSee('New invoice')
            ->waitFor('input[name="lines[0][description]"]', 10);

        $browser->script([
            "document.querySelector('input[name=issue_date]').value = '2026-01-01'",
            "document.querySelector('input[name=due_date]').value = '2026-01-31'",
        ]);

        $browser->type('lines[0][description]', 'Dusk lifecycle line')
            ->clear('lines[0][quantity]')
            ->type('lines[0][quantity]', '2')
            ->clear('lines[0][unit_price]')
            ->type('lines[0][unit_price]', '150');

        // Confirm the typed values actually landed before waiting on the
        // Alpine-computed total - a dropped keystroke on a slow runner would
        // otherwise surface as a confusing "waited for text 360.00" timeout
        // instead of pointing at the field that never got its value.
        $browser->assertInputValue('lines[0][quantity]', '2')
            ->assertInputValue('lines[0][unit_price]', '150');

        $browser->waitForText('360.00', 5)
            ->press('Create invoice')
            ->waitUntil('window.location.pathname.match(/^\\/invoices\\/\\d+\\/edit$/)', 10);

        $browser->assertSee('Save changes')
            ->assertInputValue('lines[0][description]', 'Dusk lifecycle line');

        $invoiceShowUrl = str_replace('/edit', '', $browser->driver->getCurrentURL());

        $browser->visit($invoiceShowUrl)
            ->assertSee('Draft');

        $browser->waitForReload(fn (Browser $browser) => $browser->press('Send'))
            ->assertSee('Sent')
            ->assertDontSee('Delete');

        $browser->waitForReload(fn (Browser $browser) => $browser->press('Mark paid'))
            ->assertSee('Paid')
            ->assertDontSee('Send')
            ->assertDontSee('Void')
            ->assertDontSee('Mark paid');
    });
});
