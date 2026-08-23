<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\CalculateLineTotal;
use App\Actions\RecalculateInvoiceTotals;
use App\Enums\InvoiceStatus;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Mcp\Support\Idempotency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateInvoice extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'invoices.update';

    protected string $description = 'Replace a draft invoice\'s details and line items. Only draft invoices can be edited; totals are always recalculated server-side.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->required()->description('The id of the draft invoice to update.'),
            'customer_id' => $schema->integer()->required(),
            'issue_date' => $schema->string()->format('date')->required(),
            'due_date' => $schema->string()->format('date')->required(),
            'currency' => $schema->string()->required(),
            'tax_rate' => $schema->number()->required(),
            'notes' => $schema->string(),
            'lines' => $schema->array()
                ->items($schema->object([
                    'description' => $schema->string()->required(),
                    'quantity' => $schema->number()->required(),
                    'unit_price' => $schema->number()->required(),
                ]))
                ->required()
                ->description('The full set of line items; replaces all existing lines.'),
            'idempotency_key' => $schema->string()
                ->description('Optional. Reusing the same key within 24h replays the original result instead of repeating the update.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'number' => $schema->string()->required(),
            'total' => $schema->number()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'size:3'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $invoice = Invoice::query()->find($data['invoice_id']);

        if (! $invoice instanceof Invoice) {
            return Response::error("No invoice was found with id {$data['invoice_id']}.");
        }

        if ($error = $this->authorizeTool($request, 'update', $invoice)) {
            return $error;
        }

        if ($invoice->status !== InvoiceStatus::Draft) {
            return Response::error("Invoice {$invoice->number} is {$invoice->status->value} and can no longer be edited. Only draft invoices can be updated.");
        }

        $customer = Customer::query()->find($data['customer_id']);

        if (! $customer instanceof Customer) {
            return Response::error("No customer was found with id {$data['customer_id']}.");
        }

        /** @var User $user */
        $user = $request->user();

        return app(Idempotency::class)->remember($this->name(), $data['idempotency_key'] ?? null, $user->organisation_id, function () use ($data, $invoice) {
            return $this->applyUpdate($data, $invoice);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyUpdate(array $data, Invoice $invoice): ResponseFactory
    {
        DB::transaction(function () use ($data, $invoice) {
            $invoice->update(collect($data)->except(['invoice_id', 'lines', 'idempotency_key'])->all());

            $invoice->lines()->delete();

            foreach ($data['lines'] as $position => $line) {
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => app(CalculateLineTotal::class)((float) $line['quantity'], (float) $line['unit_price']),
                    'position' => $position,
                ]);
            }
        });

        app(RecalculateInvoiceTotals::class)($invoice);

        $summary = Response::text("Updated invoice {$invoice->number}: {$invoice->currency} {$invoice->total}.");

        return Response::make($summary)->withStructuredContent([
            'id' => $invoice->id,
            'number' => $invoice->number,
            'total' => (float) $invoice->total,
        ]);
    }
}
