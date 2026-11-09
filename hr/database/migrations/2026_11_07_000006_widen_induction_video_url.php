<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('induction_modules')) {
            return;
        }

        $columns = Schema::getColumnListing('induction_modules');

        if (! in_array('video_url', $columns, true) || ! in_array('quiz', $columns, true)) {
            Schema::table('induction_modules', function (Blueprint $table) use ($columns) {
                if (! in_array('quiz', $columns, true)) {
                    $table->json('quiz')->nullable();
                }
                if (! in_array('video_url', $columns, true)) {
                    $table->text('video_url')->nullable();
                }
            });
        }

        if (in_array('video_url', $columns, true)) {
            $column = collect(Schema::getColumns('induction_modules'))->firstWhere('name', 'video_url');
            $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));
            if (! str_contains($type, 'text')) {
                Schema::table('induction_modules', function (Blueprint $table) {
                    $table->text('video_url')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('induction_modules') || ! Schema::hasColumn('induction_modules', 'video_url')) {
            return;
        }

        Schema::table('induction_modules', function (Blueprint $table) {
            $table->string('video_url')->nullable()->change();
        });
    }
};
