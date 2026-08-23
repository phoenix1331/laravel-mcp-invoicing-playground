<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\TransitionInvoiceStatus;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\Invoice;
use DomainException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent(false)]
class SendInvoice extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'invoices.send';

    protected string $description = 'Transition a draft invoice to sent. The invoice must have at least one line.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->required()->description('The id of the draft invoice to send.'),
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

        if ($error = $this->authorizeTool($request, 'update', $invoice)) {
            return $error;
        }

        try {
            app(TransitionInvoiceStatus::class)->send($invoice);
        } catch (DomainException $exception) {
            return Response::error($exception->getMessage());
        }

        $summary = Response::text("Invoice {$invoice->number} has been sent.");

        return Response::make($summary)->withStructuredContent([
            'id' => $invoice->id,
            'status' => $invoice->status->value,
        ]);
    }
}
