<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\UserRole;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListTeam extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'team.list';

    protected string $description = 'List the members of the caller\'s organisation, with their roles.';

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'members' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->integer()->required(),
                    'name' => $schema->string()->required(),
                    'email' => $schema->string()->required(),
                    'role' => $schema->string()->enum(UserRole::class)->required(),
                ]))
                ->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->authorizeTool($request, 'viewAny', User::class)) {
            return $error;
        }

        /** @var User $user */
        $user = $request->user();

        $members = User::query()
            ->where('organisation_id', $user->organisation_id)
            ->orderBy('name')
            ->get();

        $data = [
            'members' => $members->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role->value,
            ])->all(),
        ];

        $summary = Response::text("{$members->count()} team member(s).");

        return Response::make($summary)->withStructuredContent($data);
    }
}
