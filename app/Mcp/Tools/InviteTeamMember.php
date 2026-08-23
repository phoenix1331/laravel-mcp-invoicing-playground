<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\UserRole;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Mcp\Concerns\IsWriteTool;
use App\Mcp\Support\Idempotency;
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
    use AuthorizesToolAccess, IsWriteTool;

    protected string $name = 'team.invite';

    protected string $description = 'Add a new member to the caller\'s organisation with a generated temporary password. Owner role only. The password is returned once and must be relayed to the new member out of band - it cannot be retrieved again.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'email' => $schema->string()->required()->description('The new member\'s email address.'),
            'role' => $schema->string()->enum(UserRole::class)->required(),
            'idempotency_key' => $schema->string()
                ->description('Optional. Reusing the same key within 24h replays the original result, so a retry does not fail on the email already being taken by the member just created.'),
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

        /** @var User $user */
        $user = $request->user();

        $idempotencyKey = $request->get('idempotency_key');
        $idempotencyKey = is_string($idempotencyKey) ? $idempotencyKey : null;

        return app(Idempotency::class)->remember($this->name(), $idempotencyKey, $user->organisation_id, function () use ($request, $user) {
            return $this->inviteMember($request, $user);
        });
    }

    private function inviteMember(Request $request, User $user): ResponseFactory
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
        ]);

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
