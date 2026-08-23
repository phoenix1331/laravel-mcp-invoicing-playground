<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\Customer;
use App\Models\Organisation;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function authorizesToolAccessSubject(): object
{
    return new class
    {
        use AuthorizesToolAccess;

        public function check(Request $request, string $ability, mixed $arguments = []): ?Response
        {
            return $this->authorizeTool($request, $ability, $arguments);
        }
    };
}

it('returns an error response when no user is authenticated', function () {
    $subject = authorizesToolAccessSubject();
    $request = new Request;

    $response = $subject->check($request, 'viewAny', Customer::class);

    expect($response)->not->toBeNull();
});

it('returns null when the user is authorised for the ability', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Owner]);
    $this->actingAs($user);

    $subject = authorizesToolAccessSubject();
    $request = new Request;

    $response = $subject->check($request, 'create', Customer::class);

    expect($response)->toBeNull();
});

it('returns an error response when the user is not authorised for the ability', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Viewer]);
    $this->actingAs($user);

    $subject = authorizesToolAccessSubject();
    $request = new Request;

    $response = $subject->check($request, 'create', Customer::class);

    expect($response)->not->toBeNull();
});

it('checks authorisation against a specific model instance', function () {
    $acme = Organisation::factory()->create();
    $globex = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);
    $this->actingAs($user);

    $ownCustomer = Customer::factory()->create(['organisation_id' => $acme->id]);
    $otherCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);

    $subject = authorizesToolAccessSubject();
    $request = new Request;

    expect($subject->check($request, 'view', $ownCustomer))->toBeNull()
        ->and($subject->check($request, 'view', $otherCustomer))->not->toBeNull();
});
