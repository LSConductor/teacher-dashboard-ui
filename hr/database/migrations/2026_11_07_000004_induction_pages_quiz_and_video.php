<?php

use App\Hr\InductionPack;
use App\Models\InductionModule;
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

        Schema::table('induction_modules', function (Blueprint $table) {
            if (! Schema::hasColumn('induction_modules', 'quiz')) {
                $table->json('quiz')->nullable();
            }
            if (! Schema::hasColumn('induction_modules', 'video_url')) {
                $table->string('video_url')->nullable();
            }
        });

        InductionModule::query()->each(function (InductionModule $module) {
            $updates = [];
            $pack = InductionPack::body($module->title);
            if ($pack !== $module->title && ($module->body === $module->title || strlen((string) $module->body) < 80)) {
                $updates['body'] = $pack;
            }
            if (empty($module->quiz)) {
                $quiz = InductionPack::quiz($module->title);
                if ($quiz !== []) {
                    $updates['quiz'] = $quiz;
                }
            }
            if ($updates !== []) {
                $module->update($updates);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('induction_modules')) {
            return;
        }

        Schema::table('induction_modules', function (Blueprint $table) {
            foreach (['video_url', 'quiz'] as $column) {
                if (Schema::hasColumn('induction_modules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
