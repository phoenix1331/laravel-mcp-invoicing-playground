<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organisation;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $organisation = Organisation::factory()->create();
    $customer = Customer::factory()->create(['organisation_id' => $organisation->id]);
    $this->invoice = Invoice::factory()->create(['organisation_id' => $organisation->id, 'customer_id' => $customer->id, 'number' => 'INV-0001']);
    InvoiceLine::factory()->for($this->invoice)->create();
});

it('downloads the pdf via a valid signed url without authentication', function () {
    $url = URL::temporarySignedRoute('invoices.pdf.signed', now()->addMinutes(15), ['invoice' => $this->invoice->id]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertHeader('content-disposition');
    expect($response->headers->get('content-disposition'))->toContain('INV-0001.pdf');
});

it('refuses a request with an invalid signature', function () {
    $url = URL::temporarySignedRoute('invoices.pdf.signed', now()->addMinutes(15), ['invoice' => $this->invoice->id]);

    $this->get($url.'&tampered=1')->assertForbidden();
});

it('refuses a request with an expired signature', function () {
    $url = URL::temporarySignedRoute('invoices.pdf.signed', now()->subMinute(), ['invoice' => $this->invoice->id]);

    $this->get($url)->assertForbidden();
});

it('refuses a second request with the same signature', function () {
    $url = URL::temporarySignedRoute('invoices.pdf.signed', now()->addMinutes(15), ['invoice' => $this->invoice->id]);

    $this->get($url)->assertOk();
    $this->get($url)->assertForbidden();
});
