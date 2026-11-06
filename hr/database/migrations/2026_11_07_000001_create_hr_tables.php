<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cloudways MySQL may already have the tables created before the
        // over-long unique index failed. Each step is safe to run again.
        if (! Schema::hasTable('schools')) {
            $this->schools();
        }

        $this->extendUsers();

        if (! Schema::hasTable('people')) {
            $this->people();
        }
        if (! Schema::hasTable('accreditations')) {
            $this->accreditations();
        }
        if (! Schema::hasTable('wwcc_checks')) {
            $this->wwccChecks();
        }
        if (! Schema::hasTable('induction_modules')) {
            $this->inductionModules();
        }
        if (! Schema::hasTable('induction_acknowledgements')) {
            $this->inductionAcknowledgements();
        } elseif (! Schema::hasIndex('induction_acknowledgements', 'ack_person_module_version_unique')) {
            Schema::table('induction_acknowledgements', function (Blueprint $table) {
                $table->unique(
                    ['person_id', 'module_id', 'module_version'],
                    'ack_person_module_version_unique'
                );
            });
        }
        if (! Schema::hasTable('contract_templates')) {
            $this->contractTemplates();
        }
        if (! Schema::hasTable('contracts')) {
            $this->contracts();
        }
        if (! Schema::hasTable('bookings')) {
            $this->bookings();
        }
        if (! Schema::hasTable('unavailabilities')) {
            $this->unavailabilities();
        }
        if (! Schema::hasTable('day_notes')) {
            $this->dayNotes();
        }
        if (! Schema::hasTable('pd_entries')) {
            $this->pdEntries();
        }
        if (! Schema::hasTable('invites')) {
            $this->invites();
        }
        if (! Schema::hasTable('audit_logs')) {
            $this->auditLogs();
        }
    }

    private function schools(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('Australia/Sydney');
            $table->timestamps();
        });
    }

    private function extendUsers(): void
    {
        $existing = Schema::getColumnListing('users');

        if (! in_array('school_id', $existing, true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('school_id')->nullable()->after('id')->constrained();
            });
        }
        if (! in_array('role', $existing, true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('casual')->after('email');
            });
        }
        if (! in_array('neighbourhood', $existing, true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('neighbourhood')->nullable()->after('role');
            });
        }
        if (! in_array('mobile', $existing, true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('mobile')->nullable()->after('neighbourhood');
            });
        }
        if (! in_array('person_id', $existing, true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('person_id')->nullable()->index()->after('neighbourhood');
            });
        }
    }

    private function people(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->string('legal_name');
            $table->string('preferred_name');
            $table->string('email');
            $table->string('mobile')->nullable();
            $table->string('postal_address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_mobile')->nullable();
            $table->string('neighbourhood')->nullable();
            $table->json('stages')->nullable();
            $table->string('status')->default('applicant');
            $table->text('right_to_work')->nullable();
            $table->string('heard_about')->nullable();
            $table->text('adjustments')->nullable();
            $table->boolean('payroll_declaration_lodged')->default(false);
            $table->boolean('blocked')->default(false);
            $table->string('block_reason')->nullable();
            $table->foreignId('mentor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'email']);
        });
    }

    private function accreditations(): void
    {
        Schema::create('accreditations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('nesa_number')->nullable();
            $table->string('level')->nullable();
            $table->string('status')->default('active');
            $table->date('maintenance_end')->nullable();
            $table->string('fee_year')->nullable();
            $table->timestamp('etams_checked_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('alignment')->default('pending');
            $table->timestamp('alignment_decided_at')->nullable();
            $table->foreignId('alignment_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('casual_evidence_on_file')->default(false);
            $table->timestamps();
        });
    }

    private function wwccChecks(): void
    {
        Schema::create('wwcc_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('wwcc_number')->nullable();
            $table->string('name_on_check')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('outcome')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    private function inductionModules(): void
    {
        Schema::create('induction_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->string('title');
            $table->text('body');
            $table->string('phase');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    private function inductionAcknowledgements(): void
    {
        Schema::create('induction_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('induction_modules')->cascadeOnDelete();
            $table->unsignedInteger('module_version');
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            // Default name is 68 characters. MySQL stops at 64.
            $table->unique(
                ['person_id', 'module_id', 'module_version'],
                'ack_person_module_version_unique'
            );
        });
    }

    private function contractTemplates(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->string('key');
            $table->string('name');
            $table->text('body');
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'key', 'version']);
        });
    }

    private function contracts(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('contract_templates');
            $table->unsignedInteger('version');
            $table->text('merged_body');
            $table->string('status')->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }

    private function bookings(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->date('booked_on');
            $table->string('class_label');
            $table->string('plan_url')->nullable();
            $table->string('program_source')->default('wordpress');
            $table->string('external_program_id')->nullable();
            $table->string('light_at_booking');
            $table->text('conductor_note')->nullable();
            $table->text('covering_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    private function unavailabilities(): void
    {
        Schema::create('unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason')->nullable();
            $table->string('status')->default('accepted');
            $table->timestamps();
        });
    }

    private function dayNotes(): void
    {
        Schema::create('day_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->string('descriptors')->nullable();
            $table->timestamps();
        });
    }

    private function pdEntries(): void
    {
        Schema::create('pd_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->date('occurred_on');
            $table->string('name');
            $table->string('provider')->nullable();
            $table->decimal('hours', 5, 2);
            $table->string('standards')->nullable();
            $table->string('activity_type')->nullable();
            $table->timestamps();
        });
    }

    private function invites(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    private function auditLogs(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('invites');
        Schema::dropIfExists('pd_entries');
        Schema::dropIfExists('day_notes');
        Schema::dropIfExists('unavailabilities');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_templates');
        Schema::dropIfExists('induction_acknowledgements');
        Schema::dropIfExists('induction_modules');
        Schema::dropIfExists('wwcc_checks');
        Schema::dropIfExists('accreditations');
        Schema::dropIfExists('people');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
            $table->dropColumn(['role', 'neighbourhood', 'mobile', 'person_id']);
        });
        Schema::dropIfExists('schools');
    }
};
