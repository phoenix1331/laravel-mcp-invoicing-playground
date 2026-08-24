<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Mcp\Support\UntrustedText;
use App\Models\Customer;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('A single customer with their invoice history, as readable markdown.')]
class CustomerResource extends Resource implements HasUriTemplate
{
    use AuthorizesToolAccess;

    protected string $mimeType = 'text/markdown';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('customer://{customerId}');
    }

    public function handle(Request $request): Response
    {
        $customer = Customer::query()->find($request->get('customerId'));

        if (! $customer instanceof Customer) {
            return Response::error("No customer was found with id {$request->get('customerId')}.");
        }

        if ($error = $this->authorizeTool($request, 'view', $customer)) {
            return $error;
        }

        $customer->load(['invoices' => fn ($query) => $query->orderByDesc('issue_date')]);

        $invoices = $customer->invoices->isEmpty()
            ? '_No invoices yet._'
            : $customer->invoices->map(fn ($invoice): string => sprintf(
                '| %s | %s | %s | %s %s |',
                $invoice->number,
                $invoice->status->value,
                $invoice->issue_date->toDateString(),
                $invoice->currency,
                $invoice->total,
            ))->implode("\n");

        $table = $customer->invoices->isEmpty()
            ? $invoices
            : "| Number | Status | Issue date | Total |\n|---|---|---|---|\n{$invoices}";

        $name = UntrustedText::wrap($customer->name);
        $email = UntrustedText::wrap($customer->email);
        $address = UntrustedText::wrap($customer->address);

        return Response::text(<<<MARKDOWN
            # {$name}

            Email: {$email}
            Address: {$address}

            ## Invoices

            {$table}
            MARKDOWN);
    }
}
