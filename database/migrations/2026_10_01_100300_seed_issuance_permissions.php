<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\{Permission, Role};

/**
 * Issuance replaces Yarn Issue. Create issuances.* permissions and give them
 * to every role that already held the matching yarn_issues.* permission,
 * so nobody loses access after deploy.
 */
return new class extends Migration
{
    private array $actions = ['index', 'create', 'edit', 'delete', 'print'];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->actions as $action) {
            $new = Permission::firstOrCreate(['name' => "issuances.{$action}", 'guard_name' => 'web']);
            $old = Permission::where('name', "yarn_issues.{$action}")->first();
            if ($old) {
                foreach ($old->roles as $role) $role->givePermissionTo($new);
            }
        }

        if ($super = Role::where('name', 'superadmin')->first()) {
            $super->givePermissionTo(Permission::where('name', 'like', 'issuances.%')->get());
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'issuances.%')->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
