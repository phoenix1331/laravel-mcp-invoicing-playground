<?php

declare(strict_types=1);

use App\Actions\AllocateInvoiceNumber;
use App\Models\Invoice;
use App\Models\Organisation;

it('allocates the first invoice number as INV-0001', function () {
    $organisation = Organisation::factory()->create();

    $number = app(AllocateInvoiceNumber::class)($organisation);

    expect($number)->toBe('INV-0001');
});

it('allocates sequential numbers as invoices are created', function () {
    $organisation = Organisation::factory()->create();
    $allocate = app(AllocateInvoiceNumber::class);

    $first = $allocate($organisation);
    Invoice::factory()->create(['organisation_id' => $organisation->id, 'number' => $first]);

    $second = $allocate($organisation);
    Invoice::factory()->create(['organisation_id' => $organisation->id, 'number' => $second]);

    $third = $allocate($organisation);

    expect([$first, $second, $third])->toBe(['INV-0001', 'INV-0002', 'INV-0003']);
});

it('numbers each organisation independently', function () {
    $acme = Organisation::factory()->create();
    $globex = Organisation::factory()->create();
    $allocate = app(AllocateInvoiceNumber::class);

    $acmeFirst = $allocate($acme);
    Invoice::factory()->create(['organisation_id' => $acme->id, 'number' => $acmeFirst]);

    $globexFirst = $allocate($globex);

    expect($acmeFirst)->toBe('INV-0001')
        ->and($globexFirst)->toBe('INV-0001');
});
