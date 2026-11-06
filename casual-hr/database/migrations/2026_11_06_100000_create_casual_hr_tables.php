<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neighbourhoods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->string('name');
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('neighbourhood_id')->references('id')->on('neighbourhoods');
        });

        DB::statement('CREATE UNIQUE INDEX users_google_subject_per_school ON users (school_id, google_subject) WHERE google_subject IS NOT NULL');

        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->string('legal_name');
            $table->string('preferred_name');
            $table->string('email');
            $table->string('mobile');
            $table->text('postal_address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_mobile')->nullable();
            $table->foreignId('neighbourhood_id')->nullable()->constrained('neighbourhoods');
            $table->jsonb('stages')->default('[]');
            $table->string('employment_status', 32);
            $table->string('google_subject')->nullable();
            $table->string('payroll_reference')->nullable();
            // Later phases write these. The columns exist so those phases do not alter people.
            $table->timestampTz('engagement_signed_at')->nullable();
            $table->timestampTz('day_one_induction_completed_at')->nullable();
            $table->date('later_induction_window_ends_on')->nullable();
            $table->date('pd_conversation_due_on')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'email']);
            $table->index(['school_id', 'employment_status']);
        });

        DB::statement("ALTER TABLE people ADD CONSTRAINT people_employment_status_check CHECK (employment_status IN ('applicant', 'casual_pool', 'ended'))");
        DB::statement('CREATE UNIQUE INDEX people_google_subject_per_school ON people (school_id, google_subject) WHERE google_subject IS NOT NULL');

        Schema::create('mentorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('mentor_person_id')->constrained('people')->cascadeOnDelete();
            $table->boolean('is_accreditation_supervisor')->default(false);
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'mentor_person_id']);
        });

        DB::statement('CREATE UNIQUE INDEX mentorships_one_open ON mentorships (school_id, person_id) WHERE ended_on IS NULL');

        Schema::create('accreditations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('nesa_number')->nullable();
            $table->string('level', 32)->nullable();
            $table->string('status', 32)->nullable();
            $table->date('maintenance_start')->nullable();
            $table->date('maintenance_end')->nullable();
            $table->boolean('casual_evidence_given')->default(false);
            $table->unsignedSmallInteger('fee_year')->nullable();
            $table->timestampTz('etams_checked_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('alignment_decision', 32)->default('pending');
            $table->timestampTz('alignment_decided_at')->nullable();
            $table->foreignId('alignment_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delegate_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('other_employer_arrangement')->nullable();
            $table->text('evidence_reference')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'person_id']);
        });

        DB::statement("ALTER TABLE accreditations ADD CONSTRAINT accreditations_level_check CHECK (level IS NULL OR level IN ('conditional', 'provisional', 'proficient', 'highly_accomplished', 'lead'))");
        DB::statement("ALTER TABLE accreditations ADD CONSTRAINT accreditations_status_check CHECK (status IS NULL OR status IN ('active', 'leave_of_absence', 'non_practising', 'suspended', 'cancelled'))");
        DB::statement("ALTER TABLE accreditations ADD CONSTRAINT accreditations_alignment_check CHECK (alignment_decision IN ('pending', 'approved', 'declined'))");
        DB::statement('CREATE UNIQUE INDEX accreditations_one_current ON accreditations (school_id, person_id) WHERE superseded_at IS NULL');

        Schema::create('working_with_children_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('wwcc_number');
            $table->string('name_used');
            $table->date('date_of_birth');
            $table->string('outcome', 32);
            $table->timestampTz('verified_at')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('app_number_blocked')->default(false);
            $table->boolean('bar_blocked')->default(false);
            $table->boolean('interim_bar_blocked')->default(false);
            $table->boolean('expired_blocked')->default(false);
            $table->timestampTz('superseded_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'expires_on']);
        });

        DB::statement("ALTER TABLE working_with_children_checks ADD CONSTRAINT wwcc_outcome_check CHECK (outcome IN ('pending', 'verified', 'not_verified', 'barred', 'interim_bar', 'expired', 'app_number'))");
        DB::statement('CREATE UNIQUE INDEX wwcc_one_current ON working_with_children_checks (school_id, person_id) WHERE superseded_at IS NULL');

        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'person_id']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('neighbourhood_id')->nullable()->constrained('neighbourhoods');
            $table->date('on_date');
            $table->string('class_or_group');
            $table->foreignId('director_user_id')->nullable()->constrained('users')->nullOnDelete();
            // WordPress program post id (ls-tp/v1/programs). Not a Strapi id.
            $table->string('wordpress_program_id')->nullable();
            $table->string('light_at_booking', 16)->nullable();
            $table->text('covering_note')->nullable();
            $table->text('conductor_note')->nullable();
            $table->foreignId('conductor_note_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('conductor_note_at')->nullable();
            $table->string('status', 16);
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('booked_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'on_date']);
            $table->index('wordpress_program_id');
        });

        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('held', 'booked', 'cancelled'))");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_light_check CHECK (light_at_booking IS NULL OR light_at_booking IN ('green', 'amber', 'red'))");
        DB::statement("CREATE UNIQUE INDEX bookings_one_open_day ON bookings (school_id, person_id, on_date) WHERE status <> 'cancelled'");

        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->timestampTz('occurred_at');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('action');
            $table->string('record_type')->nullable();
            $table->string('record_id')->nullable();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->jsonb('metadata')->default('{}');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'occurred_at']);
            $table->index(['school_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('invites');
        Schema::dropIfExists('working_with_children_checks');
        Schema::dropIfExists('accreditations');
        Schema::dropIfExists('mentorships');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['neighbourhood_id']);
        });
        Schema::dropIfExists('people');
        Schema::dropIfExists('neighbourhoods');
    }
};
