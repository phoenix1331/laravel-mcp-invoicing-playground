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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent(false)]
class CreateCustomer extends Tool
{
    use AuthorizesToolAccess, IsWriteTool;

    protected string $name = 'customers.create';

    protected string $description = 'Create a new customer in the caller\'s organisation.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'email' => $schema->string()->description('Optional email address.'),
            'address' => $schema->string()->description('Optional postal address.'),
            'idempotency_key' => $schema->string()
                ->description('Optional. Reusing the same key within 24h replays the original result instead of creating a second customer.'),
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
        if ($error = $this->authorizeTool($request, 'create', Customer::class)) {
            return $error;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return app(Idempotency::class)->remember($this->name(), $data['idempotency_key'] ?? null, $user->organisation_id, function () use ($data, $user) {
            return $this->createCustomer($data, $user);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createCustomer(array $data, User $user): ResponseFactory
    {
        $customer = Customer::create([
            ...collect($data)->except('idempotency_key')->all(),
            'organisation_id' => $user->organisation_id,
        ]);

        $summary = Response::text("Created customer {$customer->name}.");

        return Response::make($summary)->withStructuredContent([
            'id' => $customer->id,
            'name' => $customer->name,
        ]);
    }
}
