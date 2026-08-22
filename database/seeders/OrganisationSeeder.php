<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganisationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $acme = Organisation::firstOrCreate(
            ['slug' => 'acme'],
            ['name' => 'Acme Ltd'],
        );

        $globex = Organisation::firstOrCreate(
            ['slug' => 'globex'],
            ['name' => 'Globex Inc'],
        );

        $this->createUser($acme, 'Acme Owner', 'owner@acme.test', UserRole::Owner);
        $this->createUser($acme, 'Acme Member', 'member@acme.test', UserRole::Member);
        $this->createUser($acme, 'Acme Viewer', 'viewer@acme.test', UserRole::Viewer);

        $this->createUser($globex, 'Globex Owner', 'owner@globex.test', UserRole::Owner);
    }

    private function createUser(Organisation $organisation, string $name, string $email, UserRole $role): void
    {
        User::firstOrCreate(
            ['email' => $email],
            [
                'organisation_id' => $organisation->id,
                'name' => $name,
                'password' => 'password',
                'role' => $role,
            ],
        );
    }
}
