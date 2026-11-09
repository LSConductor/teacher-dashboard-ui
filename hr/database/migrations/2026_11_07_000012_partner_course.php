<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_pages')) {
            Schema::create('partner_pages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained();
                $table->string('slug');
                $table->string('title');
                $table->text('body');
                $table->string('video_url', 2000)->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['school_id', 'slug'], 'partner_page_school_slug');
            });
        }

        if (! Schema::hasTable('partner_checks')) {
            Schema::create('partner_checks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained();
                $table->json('quiz');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique('school_id', 'partner_check_school');
            });
        }

        if (! Schema::hasTable('partners')) {
            Schema::create('partners', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained();
                $table->string('token')->unique();
                $table->string('preferred_name');
                $table->string('email');
                $table->string('mobile')->nullable();
                $table->string('relationship');
                $table->string('child_name');
                $table->string('neighbourhood');
                $table->json('interests')->nullable();
                $table->text('strengths')->nullable();
                $table->string('work_style')->nullable();
                $table->string('with_children')->nullable();
                $table->string('availability')->nullable();
                $table->json('seen')->nullable();
                $table->unsignedInteger('quiz_version_passed')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->string('volunteer_status')->default('pending');
                $table->string('volunteer_number')->nullable();
                $table->date('volunteer_verified_on')->nullable();
                $table->date('volunteer_expires_on')->nullable();
                $table->foreignId('volunteer_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['school_id', 'email'], 'partners_school_email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
        Schema::dropIfExists('partner_checks');
        Schema::dropIfExists('partner_pages');
    }
};
