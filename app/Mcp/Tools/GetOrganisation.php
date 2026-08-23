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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetOrganisation extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'organisation.get';

    protected string $description = 'Get the caller\'s organisation settings.';

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'slug' => $schema->string()->required(),
            'address' => $schema->string()->nullable(),
            'vat_number' => $schema->string()->nullable(),
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

        if ($error = $this->authorizeTool($request, 'view', $organisation)) {
            return $error;
        }

        $data = [
            'id' => $organisation->id,
            'name' => $organisation->name,
            'slug' => $organisation->slug,
            'address' => $organisation->address,
            'vat_number' => $organisation->vat_number,
        ];

        $summary = Response::text("Organisation: {$organisation->name}.");

        return Response::make($summary)->withStructuredContent($data);
    }
}
