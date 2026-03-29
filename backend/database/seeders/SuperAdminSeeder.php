<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SUPER_ADMIN_PASSWORD')
            ?? throw new \RuntimeException('SUPER_ADMIN_PASSWORD env variable is required');

        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'superadmin@autoservice.pe')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'tenant_id' => null,
            ]
        );
    }
}
