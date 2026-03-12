<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'superadmin@autoservice.pe'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('superadmin123'),
                'role' => 'super_admin',
                'tenant_id' => null,
            ]
        );
    }
}
