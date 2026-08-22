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

        $this->createUser($acme, 'Acme Owner', 'user1@email.com', UserRole::Owner);
        $this->createUser($acme, 'Acme Member', 'user2@email.com', UserRole::Member);
        $this->createUser($acme, 'Acme Viewer', 'user3@email.com', UserRole::Viewer);

        $this->createUser($globex, 'Globex Owner', 'user4@email.com', UserRole::Owner);
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
