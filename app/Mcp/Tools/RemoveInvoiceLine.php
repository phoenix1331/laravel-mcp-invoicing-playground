<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\RecalculateInvoiceTotals;
use App\Enums\InvoiceStatus;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Mcp\Support\Idempotency;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsDestructive]
#[IsIdempotent]
class RemoveInvoiceLine extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'invoices.remove_line';

    protected string $description = 'Remove a single line item from a draft invoice. Totals are recalculated server-side.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->required()->description('The id of the draft invoice.'),
            'line_id' => $schema->integer()->required()->description('The id of the line item to remove.'),
            'idempotency_key' => $schema->string()
                ->description('Optional. Reusing the same key within 24h replays the original result, so a retry after the line was already removed does not surface as an error.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->required(),
            'total' => $schema->number()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'line_id' => ['required', 'integer'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $invoice = Invoice::query()->find($data['invoice_id']);

        if (! $invoice instanceof Invoice) {
            return Response::error("No invoice was found with id {$data['invoice_id']}.");
        }

        if ($error = $this->authorizeTool($request, 'update', $invoice)) {
            return $error;
        }

        /** @var User $user */
        $user = $request->user();

        return app(Idempotency::class)->remember($this->name(), $data['idempotency_key'] ?? null, $user->organisation_id, function () use ($data, $invoice) {
            return $this->removeLine($data, $invoice);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function removeLine(array $data, Invoice $invoice): Response|ResponseFactory
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return Response::error("Invoice {$invoice->number} is {$invoice->status->value} and can no longer be edited. Only draft invoices can have lines removed.");
        }

        $line = $invoice->lines()->find($data['line_id']);

        if (! $line instanceof InvoiceLine) {
            return Response::error("No line with id {$data['line_id']} was found on invoice {$invoice->number}.");
        }

        if ($invoice->lines()->count() <= 1) {
            return Response::error("Invoice {$invoice->number} must have at least one line; remove the invoice instead of its last line.");
        }

        $line->delete();

        app(RecalculateInvoiceTotals::class)($invoice);

        $summary = Response::text("Removed line from invoice {$invoice->number}. New total: {$invoice->currency} {$invoice->total}.");

        return Response::make($summary)->withStructuredContent([
            'invoice_id' => $invoice->id,
            'total' => (float) $invoice->total,
        ]);
    }
}
