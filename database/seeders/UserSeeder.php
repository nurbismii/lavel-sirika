<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run()
    {
        $seedPassword = $this->resolveSeedPassword();

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
                $record->password = Hash::make($seedPassword);
            }

            $record->save();
        }
    }

    private function resolveSeedPassword()
    {
        $configuredPassword = config('sirika.seed_user_password');

        if (is_string($configuredPassword) && $configuredPassword !== '') {
            return $configuredPassword;
        }

        if (app()->environment(['local', 'testing'])) {
            return 'password';
        }

        throw new RuntimeException('SIRIKA_SEED_USER_PASSWORD must be set before running UserSeeder outside local/testing environments.');
    }

    private function hasValidPasswordHash($password): bool
    {
        if (! is_string($password) || $password === '') {
            return false;
        }

        return password_get_info($password)['algo'] !== 0;
    }
}
