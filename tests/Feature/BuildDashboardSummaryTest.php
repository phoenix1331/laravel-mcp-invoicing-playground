<?php

declare(strict_types=1);

use App\Actions\BuildDashboardSummary;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->user = User::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->actingAs($this->user);
});

it('sums outstanding as the total of all sent invoices', function () {
    Invoice::factory()->create(['organisation_id' => $this->organisation->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent, 'total' => 100]);
    Invoice::factory()->create(['organisation_id' => $this->organisation->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent, 'total' => 50]);
    Invoice::factory()->create(['organisation_id' => $this->organisation->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft, 'total' => 999]);

    $summary = app(BuildDashboardSummary::class)();

    expect($summary['outstanding'])->toEqual(150);
});

it('sums overdue as sent invoices past their due date', function () {
    Invoice::factory()->create([
        'organisation_id' => $this->organisation->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Sent,
        'due_date' => Carbon::yesterday(),
        'total' => 75,
    ]);
    Invoice::factory()->create([
        'organisation_id' => $this->organisation->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Sent,
        'due_date' => Carbon::tomorrow(),
        'total' => 200,
    ]);

    $summary = app(BuildDashboardSummary::class)();

    expect($summary['overdue'])->toEqual(75);
});

it('sums paid this month as paid invoices updated in the current month', function () {
    $paidThisMonth = Invoice::factory()->create([
        'organisation_id' => $this->organisation->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Draft,
        'total' => 300,
    ]);
    $paidThisMonth->update(['status' => InvoiceStatus::Paid]);

    Invoice::factory()->create([
        'organisation_id' => $this->organisation->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Paid,
        'total' => 999,
        'updated_at' => Carbon::now()->subMonths(2),
    ]);

    $summary = app(BuildDashboardSummary::class)();

    expect($summary['paid_this_month'])->toEqual(300);
});

it('counts drafts', function () {
    Invoice::factory()->count(3)->create(['organisation_id' => $this->organisation->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);
    Invoice::factory()->create(['organisation_id' => $this->organisation->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    $summary = app(BuildDashboardSummary::class)();

    expect($summary['drafts'])->toBe(3);
});

it('builds a six-month revenue series ending with the current month', function () {
    $summary = app(BuildDashboardSummary::class)();

    expect($summary['revenue_by_month'])->toHaveCount(6)
        ->and($summary['revenue_by_month'][5]['month'])->toBe(Carbon::now()->format('M'));
});

it('only includes the current organisation in the summary', function () {
    $otherOrg = Organisation::factory()->create();
    $otherCustomer = Customer::factory()->create(['organisation_id' => $otherOrg->id]);
    Invoice::factory()->create([
        'organisation_id' => $otherOrg->id,
        'customer_id' => $otherCustomer->id,
        'status' => InvoiceStatus::Sent,
        'total' => 99999,
    ]);

    $summary = app(BuildDashboardSummary::class)();

    expect($summary['outstanding'])->toEqual(0);
});
