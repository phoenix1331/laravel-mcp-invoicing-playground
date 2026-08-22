<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class GenerateInvoicePdf
{
    /**
     * Generate (or return the cached) PDF for an invoice, storing it on the
     * local disk at invoices/{org}/{number}.pdf. Regenerated whenever the
     * invoice or its organisation have changed since the file was written.
     */
    public function __invoke(Invoice $invoice): string
    {
        $invoice->loadMissing(['lines', 'customer', 'organisation']);

        $path = $this->path($invoice);
        $disk = Storage::disk('local');

        if ($disk->exists($path) && ! $this->isStale($invoice, $disk->path($path))) {
            return $path;
        }

        $disk->makeDirectory(dirname($path));

        Pdf::view('pdf.invoice', ['invoice' => $invoice])
            ->format(Format::A4)
            ->withBrowsershot(function ($browsershot): void {
                if ($chromePath = config('laravel-pdf.browsershot.chrome_path')) {
                    $browsershot->setChromePath($chromePath);
                }

                if (config('laravel-pdf.browsershot.no_sandbox')) {
                    $browsershot->noSandbox();
                }
            })
            ->save($disk->path($path));

        return $path;
    }

    public function path(Invoice $invoice): string
    {
        return "invoices/{$invoice->organisation_id}/{$invoice->number}.pdf";
    }

    private function isStale(Invoice $invoice, string $absolutePath): bool
    {
        $generatedAt = filemtime($absolutePath);

        if ($generatedAt === false) {
            return true;
        }

        $invoiceUpdatedAt = $invoice->updated_at?->timestamp ?? 0;
        $organisationUpdatedAt = $invoice->organisation?->updated_at?->timestamp ?? 0;

        return max($invoiceUpdatedAt, $organisationUpdatedAt) > $generatedAt;
    }
}
