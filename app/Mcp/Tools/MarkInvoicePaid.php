<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\TransitionInvoiceStatus;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Mcp\Support\Idempotency;
use App\Models\Invoice;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent(false)]
class MarkInvoicePaid extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'invoices.mark_paid';

    protected string $description = 'Transition a sent invoice to paid.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->required()->description('The id of the sent invoice to mark paid.'),
            'idempotency_key' => $schema->string()
                ->description('Optional. Reusing the same key within 24h replays the original result, so a retry after the invoice was already marked paid does not surface as an error.'),
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

        return app(Idempotency::class)->remember($this->name(), $data['idempotency_key'] ?? null, $user->organisation_id, function () use ($invoice) {
            return $this->markPaid($invoice);
        });
    }

    private function markPaid(Invoice $invoice): Response|ResponseFactory
    {
        try {
            app(TransitionInvoiceStatus::class)->markPaid($invoice);
        } catch (DomainException $exception) {
            return Response::error($exception->getMessage());
        }

        $summary = Response::text("Invoice {$invoice->number} has been marked paid.");

        return Response::make($summary)->withStructuredContent([
            'id' => $invoice->id,
            'status' => $invoice->status->value,
        ]);
    }
}
