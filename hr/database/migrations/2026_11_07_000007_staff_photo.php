<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = Schema::getColumnListing('users');
        if (in_array('photo_path', $columns, true)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'photo_path')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
