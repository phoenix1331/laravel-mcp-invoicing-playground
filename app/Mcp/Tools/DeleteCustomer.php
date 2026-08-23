<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Mcp\Concerns\IsWriteTool;
use App\Mcp\Support\Idempotency;
use App\Models\Customer;
use App\Models\User;
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
    use AuthorizesToolAccess, IsWriteTool;

    protected string $name = 'customers.delete';

    protected string $description = 'Delete a customer. Only permitted if the customer has no invoices.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->required()->description('The id of the customer to delete.'),
            'idempotency_key' => $schema->string()
                ->description('Optional. Reusing the same key within 24h replays the original result, so a retry after the customer was already deleted does not surface as an error.'),
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
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Authentication is required to use this tool.');
        }

        return app(Idempotency::class)->remember($this->name(), $data['idempotency_key'] ?? null, $user->organisation_id, function () use ($data, $request) {
            return $this->deleteCustomer($data, $request);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function deleteCustomer(array $data, Request $request): Response|ResponseFactory
    {
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
