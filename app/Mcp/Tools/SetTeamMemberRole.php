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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class SetTeamMemberRole extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'team.set_role';

    protected string $description = 'Change a team member\'s role. Owner role only.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->required()->description('The id of the team member.'),
            'role' => $schema->string()->enum(UserRole::class)->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->required(),
            'role' => $schema->string()->enum(UserRole::class)->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
        ]);

        $member = User::query()->find($data['user_id']);

        if (! $member instanceof User) {
            return Response::error("No team member was found with id {$data['user_id']}.");
        }

        if ($error = $this->authorizeTool($request, 'update', $member)) {
            return $error;
        }

        $member->role = $data['role'];
        $member->save();

        $summary = Response::text("Set {$member->name}'s role to {$member->role->value}.");

        return Response::make($summary)->withStructuredContent([
            'user_id' => $member->id,
            'role' => $member->role->value,
        ]);
    }
}
