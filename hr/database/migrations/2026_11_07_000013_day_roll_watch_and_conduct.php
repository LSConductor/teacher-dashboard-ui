<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schools', 'incident_desk_url')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->string('incident_desk_url', 500)->nullable();
            });
        }

        if (! Schema::hasTable('day_codes')) {
            Schema::create('day_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained();
                $table->date('on_date');
                $table->string('code', 8);
                $table->timestamps();
                $table->unique(['school_id', 'on_date'], 'day_code_school_date');
            });
        }

        if (! Schema::hasTable('day_rolls')) {
            Schema::create('day_rolls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained();
                $table->foreignId('person_id')->constrained()->cascadeOnDelete();
                $table->date('on_date');
                $table->timestamp('signed_on_at')->nullable();
                $table->foreignId('signed_on_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('signed_off_at')->nullable();
                $table->foreignId('signed_off_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('close_note')->nullable();
                $table->timestamps();
                $table->unique(['person_id', 'on_date'], 'day_roll_person_date');
            });
        }

        if (! Schema::hasTable('conduct_flags')) {
            Schema::create('conduct_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained();
                $table->foreignId('person_id')->constrained()->cascadeOnDelete();
                $table->string('kind', 40);
                $table->date('noted_on');
                $table->string('location', 200);
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('standards_notices')) {
            Schema::create('standards_notices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained();
                $table->foreignId('person_id')->constrained()->cascadeOnDelete();
                $table->date('told_on');
                $table->date('due_on');
                $table->date('reply_on')->nullable();
                $table->text('reply')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('standards_notices');
        Schema::dropIfExists('conduct_flags');
        Schema::dropIfExists('day_rolls');
        Schema::dropIfExists('day_codes');
        if (Schema::hasColumn('schools', 'incident_desk_url')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('incident_desk_url');
            });
        }
    }
};
