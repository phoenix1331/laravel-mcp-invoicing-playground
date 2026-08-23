<?php

declare(strict_types=1);

use App\Actions\GenerateInvoicePdf;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('redirects a guest away from the pdf download', function () {
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->get(route('invoices.pdf', $invoice))->assertRedirect('/login');
});

it('lets every role download the invoice pdf', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'number' => 'INV-0001',
    ]);
    InvoiceLine::factory()->for($invoice)->create();

    $response = $this->actingAs($user)->get(route('invoices.pdf', $invoice));

    $response->assertOk();
    $response->assertHeader('content-disposition');
    expect($response->headers->get('content-disposition'))->toContain('INV-0001.pdf');
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('returns a 404 for a cross-tenant invoice pdf', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->globex->id,
        'customer_id' => $globexCustomer->id,
    ]);

    $this->actingAs($user)
        ->get(route('invoices.pdf', $invoice))
        ->assertNotFound();
});

it('stores the generated pdf at invoices/{org}/{number}.pdf on the local disk', function () {
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'number' => 'INV-0001',
    ]);
    InvoiceLine::factory()->for($invoice)->create();

    $path = app(GenerateInvoicePdf::class)($invoice);

    expect($path)->toBe("invoices/{$this->acme->id}/INV-0001.pdf");
    Storage::disk('local')->assertExists($path);
});

it('reuses the cached pdf when the invoice has not changed', function () {
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
    ]);
    InvoiceLine::factory()->for($invoice)->create();

    $generate = app(GenerateInvoicePdf::class);
    $path = $generate($invoice);

    $firstModifiedAt = Storage::disk('local')->lastModified($path);

    sleep(1);
    $generate($invoice);

    expect(Storage::disk('local')->lastModified($path))->toBe($firstModifiedAt);
});

it('embeds the organisation logo as a data uri when one is set', function () {
    Storage::fake('public');

    $logoContents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    Storage::disk('public')->put('organisations/1/logo.png', $logoContents);
    $this->acme->update(['logo_path' => 'organisations/1/logo.png']);

    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
    ]);
    InvoiceLine::factory()->for($invoice)->create();

    $html = view('pdf.invoice', ['invoice' => $invoice->load(['lines', 'customer', 'organisation'])])->render();

    expect($html)->toContain('data:image/png;base64,'.base64_encode($logoContents));
});

it('omits the logo image entirely when the organisation has none', function () {
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
    ]);
    InvoiceLine::factory()->for($invoice)->create();

    $html = view('pdf.invoice', ['invoice' => $invoice->load(['lines', 'customer', 'organisation'])])->render();

    expect($html)->not->toContain('<img');
});

it('regenerates the pdf when the invoice changes', function () {
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
    ]);
    InvoiceLine::factory()->for($invoice)->create();

    $generate = app(GenerateInvoicePdf::class);
    $path = $generate($invoice);
    $firstModifiedAt = Storage::disk('local')->lastModified($path);

    sleep(1);
    $invoice->touch();
    $generate($invoice->fresh(['lines', 'customer', 'organisation']));

    expect(Storage::disk('local')->lastModified($path))->toBeGreaterThan($firstModifiedAt);
});
