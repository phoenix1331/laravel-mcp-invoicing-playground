<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateOrganisation extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'organisation.update';

    protected string $description = 'Update the caller\'s organisation settings. Owner role only.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'address' => $schema->string()->description('Optional postal address.'),
            'vat_number' => $schema->string()->description('Optional VAT number.'),
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
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Authentication is required to use this tool.');
        }

        $organisation = $user->organisation()->firstOrFail();

        if ($error = $this->authorizeTool($request, 'update', $organisation)) {
            return $error;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
        ]);

        $organisation->update($data);

        $summary = Response::text("Updated organisation: {$organisation->name}.");

        return Response::make($summary)->withStructuredContent([
            'id' => $organisation->id,
            'name' => $organisation->name,
        ]);
    }
}
