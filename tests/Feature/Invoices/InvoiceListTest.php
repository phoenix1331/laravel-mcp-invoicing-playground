<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
    $this->user = User::factory()->create(['organisation_id' => $this->acme->id]);
});

it('redirects a guest away from the invoice list', function () {
    $this->get(route('invoices.index'))->assertRedirect('/login');
});

it('lists invoices belonging to the current organisation only', function () {
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id, 'number' => 'INV-0001']);

    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);
    Invoice::factory()->create(['organisation_id' => $this->globex->id, 'customer_id' => $globexCustomer->id, 'number' => 'INV-0002']);

    $this->actingAs($this->user)
        ->get(route('invoices.index'))
        ->assertOk()
        ->assertSee('INV-0001')
        ->assertDontSee('INV-0002');
});

it('filters invoices by status', function () {
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id, 'number' => 'INV-DRAFT', 'status' => InvoiceStatus::Draft]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id, 'number' => 'INV-PAID', 'status' => InvoiceStatus::Paid]);

    $this->actingAs($this->user)
        ->get(route('invoices.index', ['status' => 'paid']))
        ->assertOk()
        ->assertSee('INV-PAID')
        ->assertDontSee('INV-DRAFT');
});

it('filters invoices by customer', function () {
    $customerA = Customer::factory()->create(['organisation_id' => $this->acme->id, 'name' => 'Customer A']);
    $customerB = Customer::factory()->create(['organisation_id' => $this->acme->id, 'name' => 'Customer B']);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customerA->id, 'number' => 'INV-A']);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customerB->id, 'number' => 'INV-B']);

    $this->actingAs($this->user)
        ->get(route('invoices.index', ['customer_id' => $customerA->id]))
        ->assertOk()
        ->assertSee('INV-A')
        ->assertDontSee('INV-B');
});

it('filters invoices by issue date range', function () {
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id, 'number' => 'INV-JAN', 'issue_date' => '2026-01-15']);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id, 'number' => 'INV-MAR', 'issue_date' => '2026-03-15']);

    $this->actingAs($this->user)
        ->get(route('invoices.index', ['from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertOk()
        ->assertSee('INV-JAN')
        ->assertDontSee('INV-MAR');
});

it('searches invoices by number', function () {
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id, 'number' => 'INV-FINDME']);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id, 'number' => 'INV-OTHER']);

    $this->actingAs($this->user)
        ->get(route('invoices.index', ['search' => 'FINDME']))
        ->assertOk()
        ->assertSee('INV-FINDME')
        ->assertDontSee('INV-OTHER');
});

it('searches invoices by customer name', function () {
    $findable = Customer::factory()->create(['organisation_id' => $this->acme->id, 'name' => 'Findable Corp']);
    $other = Customer::factory()->create(['organisation_id' => $this->acme->id, 'name' => 'Other Corp']);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $findable->id, 'number' => 'INV-0001']);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $other->id, 'number' => 'INV-0002']);

    $this->actingAs($this->user)
        ->get(route('invoices.index', ['search' => 'Findable']))
        ->assertOk()
        ->assertSee('INV-0001')
        ->assertDontSee('INV-0002');
});

it('sorts invoices by total ascending', function () {
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id, 'number' => 'INV-HIGH', 'total' => 500]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id, 'number' => 'INV-LOW', 'total' => 10]);

    $response = $this->actingAs($this->user)
        ->get(route('invoices.index', ['sort' => 'total', 'direction' => 'asc']));

    $response->assertOk();
    $content = $response->getContent();
    expect(strpos($content, 'INV-LOW'))->toBeLessThan(strpos($content, 'INV-HIGH'));
});

it('paginates the invoice list', function () {
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->count(25)->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id]);

    $response = $this->actingAs($this->user)->get(route('invoices.index'));

    $response->assertOk();
    expect($response->viewData('invoices')->count())->toBe(20)
        ->and($response->viewData('invoices')->total())->toBe(25);
});
