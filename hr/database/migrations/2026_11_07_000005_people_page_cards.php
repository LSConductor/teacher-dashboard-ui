<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = Schema::getColumnListing('users');
        Schema::table('users', function (Blueprint $table) use ($users) {
            if (! in_array('on_people_page', $users, true)) {
                $table->boolean('on_people_page')->default(false);
            }
            if (! in_array('people_title', $users, true)) {
                $table->string('people_title')->nullable();
            }
            if (! in_array('days_on_site', $users, true)) {
                $table->string('days_on_site')->nullable();
            }
            if (! in_array('people_note', $users, true)) {
                $table->string('people_note', 280)->nullable();
            }
        });

        if (Schema::hasTable('bookings')) {
            $bookings = Schema::getColumnListing('bookings');
            if (! in_array('teacher_name', $bookings, true)) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->string('teacher_name')->nullable()->after('class_label');
                });
            }
        }
    }

    public function down(): void
    {
        $users = Schema::getColumnListing('users');
        Schema::table('users', function (Blueprint $table) use ($users) {
            foreach (['on_people_page', 'people_title', 'days_on_site', 'people_note'] as $column) {
                if (in_array($column, $users, true)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'teacher_name')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('teacher_name');
            });
        }
    }
};
