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
            $record = User::firstOrNew(['email' => $user['email']]);

            $record->name = $user['name'];
            $record->role = $user['role'];
            $record->status = User::STATUS_ACTIVE;

            if (! $this->hasValidPasswordHash($record->password ?? null)) {
                $record->password = Hash::make('password');
            }

            $record->save();
        }
    }

    private function hasValidPasswordHash($password): bool
    {
        if (! is_string($password) || $password === '') {
            return false;
        }

        return password_get_info($password)['algo'] !== 0;
    }
}
