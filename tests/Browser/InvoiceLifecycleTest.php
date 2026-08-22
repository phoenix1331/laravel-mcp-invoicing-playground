<?php

declare(strict_types=1);

use Laravel\Dusk\Browser;

test('a member creates an invoice, sends it, marks it paid, and it becomes immutable', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->type('email', 'user2@email.com')
            ->type('password', 'password');

        $browser->waitForReload(fn (Browser $browser) => $browser->press('Log in'))
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
            ->type('lines[0][unit_price]', '150')
            ->assertSee('360.00')
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
