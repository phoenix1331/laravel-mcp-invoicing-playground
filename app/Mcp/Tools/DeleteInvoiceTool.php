<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\DeleteInvoice;
use App\Enums\InvoiceStatus;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsDestructive]
#[IsIdempotent(false)]
class DeleteInvoiceTool extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'invoices.delete';

    protected string $description = 'Delete a draft invoice outright. Any other status is voided instead, since it can no longer be deleted.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->required()->description('The id of the invoice to delete.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'status' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
        ]);

        $invoice = Invoice::query()->find($data['invoice_id']);

        if (! $invoice instanceof Invoice) {
            return Response::error("No invoice was found with id {$data['invoice_id']}.");
        }

        if ($error = $this->authorizeTool($request, 'delete', $invoice)) {
            return $error;
        }

        $wasDraft = $invoice->status === InvoiceStatus::Draft;
        $number = $invoice->number;
        $id = $invoice->id;

        $invoice = app(DeleteInvoice::class)($invoice);

        $summary = $wasDraft
            ? Response::text("Deleted draft invoice {$number}.")
            : Response::text("Invoice {$number} could not be deleted (not a draft) and was voided instead.");

        return Response::make($summary)->withStructuredContent([
            'id' => $id,
            'status' => $wasDraft ? 'deleted' : $invoice->status->value,
        ]);
    }
}
