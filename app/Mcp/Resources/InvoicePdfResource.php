<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Actions\GenerateInvoicePdf;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('An invoice PDF as a base64 blob. Prefer the signed URL from invoices.download_pdf for large documents - a blob inflates the token cost of the response.')]
class InvoicePdfResource extends Resource implements HasUriTemplate
{
    use AuthorizesToolAccess;

    protected string $mimeType = 'application/pdf';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('invoice://{invoiceId}/pdf');
    }

    public function handle(Request $request): Response
    {
        $invoice = Invoice::query()->find($request->get('invoiceId'));

        if (! $invoice instanceof Invoice) {
            return Response::error("No invoice was found with id {$request->get('invoiceId')}.");
        }

        if ($error = $this->authorizeTool($request, 'view', $invoice)) {
            return $error;
        }

        $path = app(GenerateInvoicePdf::class)($invoice);

        return Response::blob(Storage::disk('local')->get($path) ?? '');
    }
}
