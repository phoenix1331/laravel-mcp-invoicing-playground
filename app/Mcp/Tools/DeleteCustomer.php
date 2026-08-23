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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsDestructive]
#[IsIdempotent(false)]
class DeleteCustomer extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'customers.delete';

    protected string $description = 'Delete a customer. Only permitted if the customer has no invoices.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->required()->description('The id of the customer to delete.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
        ]);

        $customer = Customer::query()->find($data['customer_id']);

        if (! $customer instanceof Customer) {
            return Response::error("No customer was found with id {$data['customer_id']}.");
        }

        if ($error = $this->authorizeTool($request, 'delete', $customer)) {
            return $error;
        }

        $id = $customer->id;
        $name = $customer->name;

        $customer->delete();

        $summary = Response::text("Deleted customer {$name}.");

        return Response::make($summary)->withStructuredContent(['id' => $id]);
    }
}
