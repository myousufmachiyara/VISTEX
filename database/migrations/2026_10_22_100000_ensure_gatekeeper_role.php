<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\{Permission, Role};

/**
 * The gatekeeper role is created by the seeder, which a live system never runs. Gate passes notify it,
 * so create it here with the gate permissions (same set as the seeder). Assign it to the gate staff
 * under Roles & Permissions / Users. Safe to re-run; never removes anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) return;
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $role = Role::firstOrCreate(['name' => 'gatekeeper', 'guard_name' => 'web']);
        $names = ['locations.index', 'purchase_receivings.index', 'purchase_receivings.create',
                  'challans.index', 'challans.create', 'challans.edit', 'stock_movements.index', 'stock_movements.gate'];
        foreach ($names as $n) $role->givePermissionTo(Permission::firstOrCreate(['name' => $n, 'guard_name' => 'web']));
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void {}
};
