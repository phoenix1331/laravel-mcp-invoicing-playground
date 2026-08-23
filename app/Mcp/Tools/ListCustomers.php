<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListCustomers extends Tool
{
    use AuthorizesToolAccess;

    protected string $description = 'List the caller\'s organisation customers, with a count of their invoices.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('The page of results to return.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'customers' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->integer()->required(),
                    'name' => $schema->string()->required(),
                    'email' => $schema->string()->nullable(),
                    'address' => $schema->string()->nullable(),
                    'invoices_count' => $schema->integer()->required(),
                ]))
                ->required(),
            'current_page' => $schema->integer()->required(),
            'last_page' => $schema->integer()->required(),
            'total' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->authorizeTool($request, 'viewAny', Customer::class)) {
            return $error;
        }

        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = Customer::query()
            ->withCount('invoices')
            ->orderBy('name')
            ->paginate(20, page: (int) $request->get('page', 1));

        $customers = $paginator->getCollection()->map(fn (Customer $customer): array => [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'address' => $customer->address,
            'invoices_count' => $customer->invoices_count,
        ])->all();

        $summary = Response::text("Found {$paginator->total()} customer(s), page {$paginator->currentPage()} of {$paginator->lastPage()}.");

        return Response::make($summary)->withStructuredContent([
            'customers' => $customers,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }
}
