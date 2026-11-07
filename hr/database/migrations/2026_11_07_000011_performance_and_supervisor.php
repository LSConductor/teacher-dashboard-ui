<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('people', 'supervisor_user_id')) {
            Schema::table('people', function (Blueprint $table) {
                $table->foreignId('supervisor_user_id')->nullable()->after('mentor_user_id')->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('performance_records')) {
            return;
        }

        Schema::create('performance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->date('recorded_on')->nullable();
            $table->string('descriptors')->nullable();
            $table->text('body');
            $table->text('next_step')->nullable();
            $table->text('students_did')->nullable();
            $table->text('keep_doing')->nullable();
            $table->text('change_next')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['person_id', 'kind'], 'performance_person_kind_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_records');

        if (Schema::hasColumn('people', 'supervisor_user_id')) {
            Schema::table('people', function (Blueprint $table) {
                $table->dropConstrainedForeignId('supervisor_user_id');
            });
        }
    }
};
