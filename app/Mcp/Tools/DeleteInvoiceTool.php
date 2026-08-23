<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\DeleteInvoice;
use App\Enums\InvoiceStatus;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Mcp\Support\ConfirmationGate;
use App\Mcp\Support\Idempotency;
use App\Models\Invoice;
use App\Models\User;
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

    protected string $description = 'Delete a draft invoice outright. Any other status is voided instead, since it can no longer be deleted. Requires confirm: true.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->required()->description('The id of the invoice to delete.'),
            'confirm' => $schema->boolean()
                ->description('Must be true to actually delete. Omitted or false returns a description of the consequences instead.'),
            'idempotency_key' => $schema->string()
                ->description('Optional. Reusing the same key within 24h replays the original result, so a retry after the invoice was already deleted does not surface as an error.'),
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
            'confirm' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Authentication is required to use this tool.');
        }

        return app(Idempotency::class)->remember($this->name(), $data['idempotency_key'] ?? null, $user->organisation_id, function () use ($data, $request) {
            return $this->deleteInvoice($data, $request);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function deleteInvoice(array $data, Request $request): Response|ResponseFactory
    {
        $invoice = Invoice::query()->find($data['invoice_id']);

        if (! $invoice instanceof Invoice) {
            return Response::error("No invoice was found with id {$data['invoice_id']}.");
        }

        if ($error = $this->authorizeTool($request, 'delete', $invoice)) {
            return $error;
        }

        $consequence = $invoice->status === InvoiceStatus::Draft
            ? "delete draft invoice {$invoice->number} outright. This cannot be undone."
            : "void invoice {$invoice->number} instead of deleting it, since only draft invoices can be deleted. Voiding is permanent and the invoice cannot be revived.";

        if ($confirmation = app(ConfirmationGate::class)->requireConfirmation((bool) ($data['confirm'] ?? false), 'delete this invoice', $consequence)) {
            return $confirmation;
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
