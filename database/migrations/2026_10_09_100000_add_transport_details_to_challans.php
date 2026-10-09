<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\{Permission, Role};

/**
 * Inbound challan: optional transport details (vehicle, driver, contact),
 * and challan editing for everyone who can log challans.
 * Safe to re-run: each column is added only if missing.
 */
return new class extends Migration
{
    private array $columns = ['vehicle_no', 'driver_name', 'driver_contact'];

    public function up(): void
    {
        Schema::table('challans', function (Blueprint $table) {
            if (!Schema::hasColumn('challans', 'vehicle_no'))     $table->string('vehicle_no', 30)->nullable()->after('vendor_challan_no');
            if (!Schema::hasColumn('challans', 'driver_name'))    $table->string('driver_name', 100)->nullable()->after('vehicle_no');
            if (!Schema::hasColumn('challans', 'driver_contact')) $table->string('driver_contact', 30)->nullable()->after('driver_name');
        });

        // Gatekeepers correct their own challans: give challans.edit to every role that can log one.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $edit = Permission::firstOrCreate(['name' => 'challans.edit', 'guard_name' => 'web']);
        if ($create = Permission::where('name', 'challans.create')->first()) {
            foreach ($create->roles as $role) $role->givePermissionTo($edit);
        }
        if ($super = Role::where('name', 'superadmin')->first()) $super->givePermissionTo($edit);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('challans', function (Blueprint $table) {
            foreach ($this->columns as $col) {
                if (Schema::hasColumn('challans', $col)) $table->dropColumn($col);
            }
        });
    }
};
