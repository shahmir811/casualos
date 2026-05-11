<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create the four roles
        $roles = ['admin', 'accountant', 'manager', 'designer'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Create the default admin account
        $admin = User::firstOrCreate(
            ['email' => 'admin@casualite.com'],
            [
                'name'      => 'Sheikh Mohsin',
                'password'  => Hash::make('Admin@1234'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        $admin->assignRole('admin');

        $this->command->info('Admin account ready: admin@casualite.com / Admin@1234');
        $this->command->warn('Change the admin password immediately after first login!');

        // Create manager account
        $manager = User::firstOrCreate(
            ['email' => 'adnan@casualite.com'],
            [
                'name'      => 'Muhammad Adnan',
                'password'  => Hash::make('12345678'),
                'role'      => 'manager',
                'is_active' => true,
            ]
        );

        $manager->assignRole('manager');

        $this->command->info('Manager account ready: manager@casualite.com / 12345678');

        // Create accountant account
        $accountant = User::firstOrCreate(
            ['email' => 'ashraf@casualite.com'],
            [
                'name'      => 'Muhammad Ashraf',
                'password'  => Hash::make('12345678'),
                'role'      => 'accountant',
                'is_active' => true,
            ]
        );

        $accountant->assignRole('accountant');

        $this->command->info('Accountant account ready: accountant@casualite.com / 12345678');
    }
}
