<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = [
            ['name' => 'Super Admin SIRIKA', 'email' => 'superadmin@sirika.local', 'role' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Admin HR SIRIKA', 'email' => 'adminhr@sirika.local', 'role' => User::ROLE_ADMIN_HR],
            ['name' => 'Security SIRIKA', 'email' => 'security@sirika.local', 'role' => User::ROLE_SECURITY],
            ['name' => 'Auditor SIRIKA', 'email' => 'auditor@sirika.local', 'role' => User::ROLE_AUDITOR],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => $user['role'],
                    'status' => User::STATUS_ACTIVE,
                ]
            );
        }
    }
}
