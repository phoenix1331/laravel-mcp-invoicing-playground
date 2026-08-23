<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\UserRole;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent(false)]
class InviteTeamMember extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'team.invite';

    protected string $description = 'Add a new member to the caller\'s organisation with a generated temporary password. Owner role only. The password is returned once and must be relayed to the new member out of band - it cannot be retrieved again.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'email' => $schema->string()->required()->description('The new member\'s email address.'),
            'role' => $schema->string()->enum(UserRole::class)->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->required(),
            'email' => $schema->string()->required(),
            'temporary_password' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->authorizeTool($request, 'create', User::class)) {
            return $error;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
        ]);

        /** @var User $user */
        $user = $request->user();

        $temporaryPassword = Str::password(20);

        $member = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $temporaryPassword,
            'organisation_id' => $user->organisation_id,
            'role' => $data['role'],
        ]);

        $summary = Response::text("Added {$member->name} ({$member->email}) as {$member->role->value}. Relay the temporary password to them securely - it will not be shown again.");

        return Response::make($summary)->withStructuredContent([
            'user_id' => $member->id,
            'email' => $member->email,
            'temporary_password' => $temporaryPassword,
        ]);
    }
}
