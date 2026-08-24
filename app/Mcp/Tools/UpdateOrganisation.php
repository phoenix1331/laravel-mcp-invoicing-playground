<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Mcp\Concerns\IsWriteTool;
use App\Mcp\Support\Idempotency;
use App\Mcp\Support\UntrustedText;
use App\Models\Organisation;
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
    use AuthorizesToolAccess, IsWriteTool;

    protected string $name = 'organisation.update';

    protected string $description = 'Update the caller\'s organisation settings. Owner role only.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'address' => $schema->string()->description('Optional postal address.'),
            'vat_number' => $schema->string()->description('Optional VAT number.'),
            'idempotency_key' => $schema->string()
                ->description('Optional. Reusing the same key within 24h replays the original result instead of repeating the update.'),
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
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        return app(Idempotency::class)->remember($this->name(), $data['idempotency_key'] ?? null, $organisation->id, function () use ($data, $organisation) {
            return $this->updateOrganisation($data, $organisation);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateOrganisation(array $data, Organisation $organisation): ResponseFactory
    {
        $organisation->update(collect($data)->except('idempotency_key')->all());

        $summary = Response::text('Updated organisation: '.UntrustedText::wrap($organisation->name).'.');

        return Response::make($summary)->withStructuredContent([
            'id' => $organisation->id,
            'name' => UntrustedText::wrap($organisation->name),
        ]);
    }
}
