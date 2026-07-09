<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\RabBudget;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Roles
        $roles = [
            'LAPANGAN', 
            'ENGINEER', 
            'PURCHASING_LEGAL', 
            'VERIFIKATOR_KEU', 
            'MGR_KOMERSIAL', 
            'KEU_KANTOR', 
            'PAJAK', 
            'ACCOUNTING'
        ];
        
        $roleMap = [];
        foreach ($roles as $roleName) {
            $roleMap[$roleName] = Role::create(['role_name' => $roleName])->id;
        }

        // 2. Seed Users
        foreach ($roles as $roleName) {
            User::create([
                'name' => ucwords(strtolower(str_replace('_', ' ', $roleName))),
                'email' => strtolower($roleName) . '@erp.com',
                'password' => Hash::make('password'),
                'role_id' => $roleMap[$roleName]
            ]);
        }

        // 3. Seed initial project
        $project = Project::create([
            'project_name' => 'Proyek RSUD Mentawai',
            'location' => 'Mentawai',
            'start_date' => '2026-08-01',
        ]);

        RabBudget::create([
            'project_id' => $project->id,
            'code_item' => 'A.1.1',
            'description' => 'Semen Portland (PC) 50kg',
            'unit' => 'Zak',
            'volume' => 1000,
            'unit_price' => 75000,
            'total_price' => 75000000,
            'category' => 'Material'
        ]);

        RabBudget::create([
            'project_id' => $project->id,
            'code_item' => 'A.1.2',
            'description' => 'Besi Beton Polos',
            'unit' => 'Kg',
            'volume' => 5000,
            'unit_price' => 12500,
            'total_price' => 62500000,
            'category' => 'Material'
        ]);
    }
}