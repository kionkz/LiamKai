<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (!config('operations.setup.seed_admin', true)) {
            return;
        }

        User::updateOrCreate(
            ['username' => config('operations.setup.admin_username')],
            [
                'name' => config('operations.setup.admin_name'),
                'email' => config('operations.setup.admin_email'),
                'password' => Hash::make(config('operations.setup.admin_password')),
                'role' => 'admin',
                'account_status' => 'active',
                'must_change_password' => false,
            ]
        );
    }
}
