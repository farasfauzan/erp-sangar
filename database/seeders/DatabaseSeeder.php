<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed only the baseline access configuration.
     *
     * Operational records (projects, RAB, PO, SPK, and so on) must be created
     * through the application, rather than being reintroduced on a database reset.
     */
    public function run(): void
    {
        $roles = [
            'ADMIN',
            'LAPANGAN',
            'ENGINEER',
            'PURCHASING_LEGAL',
            'VERIFIKATOR_KEU',
            'MGR_KOMERSIAL',
            'KEU_KANTOR',
            'PAJAK',
            'ACCOUNTING',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['role_name' => $roleName]);
        }

        $adminRole = Role::where('role_name', 'ADMIN')->firstOrFail();

        User::firstOrCreate(
            ['email' => 'admin@erp.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'email_verified_at' => now(),
            ],
        );
    }
}
