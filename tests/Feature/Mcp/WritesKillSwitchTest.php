<?php

declare(strict_types=1);

use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\CreateCustomer;
use App\Mcp\Tools\CreateInvoice;
use App\Models\Customer;
use App\Models\Organisation;
use App\Models\User;
use Laravel\Mcp\Server\Transport\FakeTransporter;

/**
 * @return array<int, string>
 */
function registeredToolNames(): array
{
    $server = new InvoicingServer(new FakeTransporter);

    return $server->createContext()->tools()
        ->map(fn ($tool) => $tool->name())
        ->all();
}

it('lists write tools when the kill switch is on', function () {
    config(['mcp.writes_enabled' => true]);

    expect(registeredToolNames())
        ->toContain('invoices.create')
        ->toContain('invoices.delete')
        ->toContain('customers.create');
});

it('removes write tools from the catalogue when the kill switch is off', function () {
    config(['mcp.writes_enabled' => false]);

    $names = registeredToolNames();

    expect($names)
        ->not->toContain('invoices.create')
        ->not->toContain('invoices.update')
        ->not->toContain('invoices.add_line')
        ->not->toContain('invoices.remove_line')
        ->not->toContain('invoices.send')
        ->not->toContain('invoices.mark_paid')
        ->not->toContain('invoices.void')
        ->not->toContain('invoices.delete')
        ->not->toContain('customers.create')
        ->not->toContain('customers.update')
        ->not->toContain('customers.delete')
        ->not->toContain('organisation.update')
        ->not->toContain('team.invite')
        ->not->toContain('team.set_role');
});

it('leaves read tools in the catalogue when the kill switch is off', function () {
    config(['mcp.writes_enabled' => false]);

    expect(registeredToolNames())
        ->toContain('invoices.list')
        ->toContain('invoices.get')
        ->toContain('customers.list')
        ->toContain('customers.get')
        ->toContain('organisation.get')
        ->toContain('team.list')
        ->toContain('reports.summary')
        ->toContain('reports.aging')
        ->toContain('invoices.download_pdf');
});

it('refuses to call a write tool by name when the kill switch is off', function () {
    config(['mcp.writes_enabled' => false]);

    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);
    $customer = Customer::factory()->create(['organisation_id' => $organisation->id]);

    InvoicingServer::actingAs($user)->tool(CreateInvoice::class, [
        'customer_id' => $customer->id,
        'issue_date' => '2026-01-01',
        'due_date' => '2026-01-31',
        'currency' => 'GBP',
        'tax_rate' => 20,
        'lines' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 100]],
    ])->assertHasErrors();
});

it('still allows creating a customer once the kill switch is back on', function () {
    config(['mcp.writes_enabled' => false]);
    config(['mcp.writes_enabled' => true]);

    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    InvoicingServer::actingAs($user)->tool(CreateCustomer::class, ['name' => 'Re-enabled Co'])
        ->assertOk();
});
