<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('redirects a guest away from the organisation settings page', function () {
    $this->get(route('settings.organisation'))->assertRedirect('/login');
});

it('shows the organisation settings for any authenticated role', function (UserRole $role) {
    $organisation = Organisation::factory()->create(['name' => 'Acme Inc', 'vat_number' => 'GB123456789']);
    $user = User::factory()->create(['organisation_id' => $organisation->id, 'role' => $role]);

    $this->actingAs($user)
        ->get(route('settings.organisation'))
        ->assertOk()
        ->assertSee(['Acme Inc', 'GB123456789']);
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('allows the owner to update organisation details', function () {
    $organisation = Organisation::factory()->create(['name' => 'Old name']);
    $user = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Owner]);

    $this->actingAs($user)
        ->put(route('settings.organisation.update'), [
            'name' => 'New name',
            'address' => '1 New Street',
            'vat_number' => 'GB987654321',
        ])
        ->assertRedirect(route('settings.organisation'));

    expect($organisation->fresh())
        ->name->toBe('New name')
        ->address->toBe('1 New Street')
        ->vat_number->toBe('GB987654321');
});

it('denies member and viewer from updating organisation details', function (UserRole $role) {
    $organisation = Organisation::factory()->create(['name' => 'Old name']);
    $user = User::factory()->create(['organisation_id' => $organisation->id, 'role' => $role]);

    $this->actingAs($user)
        ->put(route('settings.organisation.update'), ['name' => 'New name'])
        ->assertForbidden();

    expect($organisation->fresh()->name)->toBe('Old name');
})->with([UserRole::Member, UserRole::Viewer]);

it('fails validation when name is missing', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Owner]);

    $this->actingAs($user)
        ->put(route('settings.organisation.update'), [])
        ->assertSessionHasErrors('name');
});

it('uploads and replaces the organisation logo', function () {
    Storage::fake('public');

    $organisation = Organisation::factory()->create(['name' => 'Acme Inc']);
    $user = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Owner]);

    $this->actingAs($user)->put(route('settings.organisation.update'), [
        'name' => 'Acme Inc',
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $organisation->refresh();

    expect($organisation->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($organisation->logo_path);

    $originalPath = $organisation->logo_path;

    $this->actingAs($user)->put(route('settings.organisation.update'), [
        'name' => 'Acme Inc',
        'logo' => UploadedFile::fake()->image('new-logo.png'),
    ]);

    $organisation->refresh();

    Storage::disk('public')->assertMissing($originalPath);
    Storage::disk('public')->assertExists($organisation->logo_path);
});

it('rejects a non-image logo upload', function () {
    Storage::fake('public');

    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Owner]);

    $this->actingAs($user)
        ->put(route('settings.organisation.update'), [
            'name' => $organisation->name,
            'logo' => UploadedFile::fake()->create('not-an-image.pdf', 100),
        ])
        ->assertSessionHasErrors('logo');
});
