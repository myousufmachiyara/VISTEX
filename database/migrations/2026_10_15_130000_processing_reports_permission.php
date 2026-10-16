<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\{Permission, Role};

/** Permission for the Processing & Costing reports (and the dashboard's money tiles). Safe to re-run. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) return;
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $p = Permission::firstOrCreate(['name' => 'reports.processing', 'guard_name' => 'web']);
        Role::where('name', 'superadmin')->first()?->givePermissionTo($p);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'reports.processing')->delete();
    }
};
