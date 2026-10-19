<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bags per issued line (record only). issuances.no_of_bags becomes the total of the lines.
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('issuance_items', 'bags')) {
            Schema::table('issuance_items', fn(Blueprint $t) => $t->decimal('bags', 12, 2)->nullable()->after('quantity'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('issuance_items', 'bags')) {
            Schema::table('issuance_items', fn(Blueprint $t) => $t->dropColumn('bags'));
        }
    }
};
