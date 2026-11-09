<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = Schema::getColumnListing('users');
        if (in_array('people_sort', $columns, true)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('people_sort')->default(0);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'people_sort')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('people_sort');
        });
    }
};
