<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

function createUserWithRole(UserRole $role, ?Organisation $organisation = null): User
{
    return User::factory()->create([
        'organisation_id' => ($organisation ?? Organisation::factory()->create())->id,
        'role' => $role,
    ]);
}
