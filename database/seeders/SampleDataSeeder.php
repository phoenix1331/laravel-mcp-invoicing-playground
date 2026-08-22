<?php

namespace Database\Seeders;

use App\Actions\AllocateInvoiceNumber;
use App\Actions\CalculateLineTotal;
use App\Actions\RecalculateInvoiceTotals;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SampleDataSeeder extends Seeder
{
    /**
     * A realistic spread of statuses across each organisation's invoices.
     *
     * @var list<InvoiceStatus>
     */
    private const STATUS_CYCLE = [
        InvoiceStatus::Paid,
        InvoiceStatus::Paid,
        InvoiceStatus::Sent,
        InvoiceStatus::Paid,
        InvoiceStatus::Draft,
        InvoiceStatus::Sent,
        InvoiceStatus::Paid,
        InvoiceStatus::Void,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organisation::all()->each(function (Organisation $organisation) {
            if ($organisation->customers()->exists()) {
                return;
            }

            $customers = Customer::factory()->count(8)->create(['organisation_id' => $organisation->id]);
            $creator = $organisation->users()->first();

            if ($creator === null) {
                return;
            }

            collect(range(1, 25))->each(
                fn (int $i) => $this->createInvoice($organisation, $customers->random(), $creator, self::STATUS_CYCLE[$i % count(self::STATUS_CYCLE)]),
            );
        });
    }

    private function createInvoice(Organisation $organisation, Customer $customer, User $creator, InvoiceStatus $status): void
    {
        $issueDate = Carbon::now()->subDays(fake()->numberBetween(0, 180));

        $invoice = Invoice::create([
            'organisation_id' => $organisation->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $creator->id,
            'number' => app(AllocateInvoiceNumber::class)($organisation),
            'status' => $status,
            'issue_date' => $issueDate,
            'due_date' => $issueDate->copy()->addDays(30),
            'currency' => 'GBP',
            'tax_rate' => 20,
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
        ]);

        collect(range(1, fake()->numberBetween(1, 4)))->each(function (int $position) use ($invoice) {
            $quantity = fake()->numberBetween(1, 5);
            $unitPrice = fake()->randomFloat(2, 20, 400);

            $invoice->lines()->create([
                'description' => fake()->sentence(4),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => app(CalculateLineTotal::class)((float) $quantity, $unitPrice),
                'position' => $position - 1,
            ]);
        });

        app(RecalculateInvoiceTotals::class)($invoice);

        if ($status === InvoiceStatus::Paid || $status === InvoiceStatus::Void) {
            $invoice->timestamps = false;
            $invoice->updated_at = $issueDate->copy()->addDays(fake()->numberBetween(1, 14));
            $invoice->save();
        }
    }
}
