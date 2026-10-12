<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Vider le cache des permissions avant le seeding
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'compagnie.view',
            'compagnie.create',
            'compagnie.edit',
            'compagnie.delete',

            'gare.view',
            'gare.create',
            'gare.edit',
            'gare.delete',

            'route.view',
            'route.create',
            'route.edit',
            'route.delete',

            'billet.view',
            'billet.refund',

            'ville.view',
            'ville.create',
            'ville.edit',

            'user.view',
            'user.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $superAdmin->syncPermissions(Permission::where('guard_name', 'web')->get());

        $adminCompagnie = Role::firstOrCreate([
            'name' => 'admin_compagnie',
            'guard_name' => 'web',
        ]);

        $adminCompagnie->syncPermissions([
            'compagnie.view',
            'gare.view',
            'gare.create',
            'gare.edit',
            'route.view',
            'route.create',
            'route.edit',
            'billet.view',
        ]);

        $agentGare = Role::firstOrCreate([
            'name' => 'agent_gare',
            'guard_name' => 'web',
        ]);

        $agentGare->syncPermissions([
            'billet.view',
        ]);

        // Vider le cache après la création des rôles et permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}