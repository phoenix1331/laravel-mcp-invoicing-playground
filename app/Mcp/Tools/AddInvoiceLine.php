<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\CalculateLineTotal;
use App\Actions\RecalculateInvoiceTotals;
use App\Enums\InvoiceStatus;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Mcp\Support\Idempotency;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent(false)]
class AddInvoiceLine extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'invoices.add_line';

    protected string $description = 'Add a single line item to a draft invoice, without touching its other lines. Totals are recalculated server-side.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->required()->description('The id of the draft invoice.'),
            'description' => $schema->string()->required(),
            'quantity' => $schema->number()->required(),
            'unit_price' => $schema->number()->required(),
            'idempotency_key' => $schema->string()
                ->description('Optional. Reusing the same key within 24h replays the original result instead of adding a second line.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->required(),
            'line_id' => $schema->integer()->required(),
            'total' => $schema->number()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
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
            return Response::error("Invoice {$invoice->number} is {$invoice->status->value} and can no longer be edited. Only draft invoices can have lines added.");
        }

        /** @var User $user */
        $user = $request->user();

        return app(Idempotency::class)->remember($this->name(), $data['idempotency_key'] ?? null, $user->organisation_id, function () use ($data, $invoice) {
            return $this->addLine($data, $invoice);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function addLine(array $data, Invoice $invoice): ResponseFactory
    {
        $nextPosition = (int) $invoice->lines()->max('position') + 1;

        $line = $invoice->lines()->create([
            'description' => $data['description'],
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
            'line_total' => app(CalculateLineTotal::class)((float) $data['quantity'], (float) $data['unit_price']),
            'position' => $nextPosition,
        ]);

        app(RecalculateInvoiceTotals::class)($invoice);

        $summary = Response::text("Added line to invoice {$invoice->number}. New total: {$invoice->currency} {$invoice->total}.");

        return Response::make($summary)->withStructuredContent([
            'invoice_id' => $invoice->id,
            'line_id' => $line->id,
            'total' => (float) $invoice->total,
        ]);
    }
}
