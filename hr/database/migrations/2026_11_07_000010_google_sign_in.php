<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'google_sub')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('google_sub', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'google_sub')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_sub']);
            $table->dropColumn('google_sub');
        });
    }
};
