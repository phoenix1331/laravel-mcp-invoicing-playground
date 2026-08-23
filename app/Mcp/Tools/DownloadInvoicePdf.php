<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Mcp\Resources\InvoicePdfResource;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\URL;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class DownloadInvoicePdf extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'invoices.download_pdf';

    protected string $description = 'Get an invoice PDF: a signed, single-use download URL (valid 15 minutes), a resource link to fetch it as a base64 blob, or both. The signed URL is the better default for large documents - a blob inflates the response token cost.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->required()->description('The id of the invoice.'),
            'format' => $schema->string()
                ->enum(['blob', 'url', 'both'])
                ->default('both')
                ->description('How to deliver the PDF: a signed download url, a resource link to a base64 blob, or both.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->required(),
            'url' => $schema->string()->description('A signed, single-use download URL, present when format is url or both.'),
            'resource_uri' => $schema->string()->description('The invoice://{invoiceId}/pdf resource URI, present when format is blob or both.'),
            'expires_in_minutes' => $schema->integer(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'format' => ['nullable', 'string', 'in:blob,url,both'],
        ]);

        $format = $data['format'] ?? 'both';

        $invoice = Invoice::query()->find($data['invoice_id']);

        if (! $invoice instanceof Invoice) {
            return Response::error("No invoice was found with id {$data['invoice_id']}.");
        }

        if ($error = $this->authorizeTool($request, 'view', $invoice)) {
            return $error;
        }

        $structured = ['invoice_id' => $invoice->id];
        $responses = [];

        if ($format === 'url' || $format === 'both') {
            $url = URL::temporarySignedRoute('invoices.pdf.signed', now()->addMinutes(15), ['invoice' => $invoice->id]);

            $structured['url'] = $url;
            $structured['expires_in_minutes'] = 15;
        }

        if ($format === 'blob' || $format === 'both') {
            $resourceUri = "invoice://{$invoice->id}/pdf";
            $structured['resource_uri'] = $resourceUri;

            /** @var InvoicePdfResource $pdfResource */
            $pdfResource = app(InvoicePdfResource::class);

            $responses[] = Response::resourceLink(
                uri: $resourceUri,
                name: "invoice-{$invoice->number}-pdf",
                mimeType: $pdfResource->mimeType(),
                description: "The PDF for invoice {$invoice->number}.",
            );
        }

        $summary = Response::text("Invoice {$invoice->number} PDF is ready.");

        return Response::make([$summary, ...$responses])->withStructuredContent($structured);
    }
}
