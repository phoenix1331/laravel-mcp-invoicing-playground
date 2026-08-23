<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetCustomer extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'customers.get';

    protected string $description = 'Get a single customer, including a summary of their invoice history, by id.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()
                ->required()
                ->description('The id of the customer to retrieve.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'email' => $schema->string()->nullable(),
            'address' => $schema->string()->nullable(),
            'invoices' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->integer()->required(),
                    'number' => $schema->string()->required(),
                    'status' => $schema->string()->required(),
                    'issue_date' => $schema->string()->format('date')->required(),
                    'total' => $schema->number()->required(),
                ]))
                ->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'customer_id' => ['required', 'integer'],
        ]);

        $customer = Customer::query()->find($request->get('customer_id'));

        if (! $customer instanceof Customer) {
            return Response::error("No customer was found with id {$request->get('customer_id')}.");
        }

        if ($error = $this->authorizeTool($request, 'view', $customer)) {
            return $error;
        }

        $customer->load(['invoices' => fn ($query) => $query->orderByDesc('issue_date')]);

        $data = [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'address' => $customer->address,
            'invoices' => $customer->invoices->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'issue_date' => $invoice->issue_date->toDateString(),
                'total' => (float) $invoice->total,
            ])->all(),
        ];

        $summary = Response::text("Customer {$customer->name} has {$customer->invoices->count()} invoice(s).");

        return Response::make($summary)->withStructuredContent($data);
    }
}
