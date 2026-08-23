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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateCustomer extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'customers.update';

    protected string $description = 'Update a customer\'s details.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->required()->description('The id of the customer to update.'),
            'name' => $schema->string()->required(),
            'email' => $schema->string()->description('Optional email address.'),
            'address' => $schema->string()->description('Optional postal address.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = Customer::query()->find($data['customer_id']);

        if (! $customer instanceof Customer) {
            return Response::error("No customer was found with id {$data['customer_id']}.");
        }

        if ($error = $this->authorizeTool($request, 'update', $customer)) {
            return $error;
        }

        $customer->update(collect($data)->except('customer_id')->all());

        $summary = Response::text("Updated customer {$customer->name}.");

        return Response::make($summary)->withStructuredContent([
            'id' => $customer->id,
            'name' => $customer->name,
        ]);
    }
}
