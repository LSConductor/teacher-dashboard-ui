<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cover_requests')) {
            Schema::create('cover_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('neighbourhood')->nullable();
                $table->string('pool')->default('casual');
                $table->date('booked_on');
                $table->string('class_label');
                $table->string('teacher_name')->nullable();
                $table->string('plan_url')->nullable();
                $table->string('start_time', 20)->nullable();
                $table->string('finish_time', 20)->nullable();
                $table->string('mode')->default('ordered');
                $table->string('status')->default('open');
                $table->text('conductor_note')->nullable();
                $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cover_asks')) {
            Schema::create('cover_asks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cover_request_id')->constrained()->cascadeOnDelete();
                $table->foreignId('person_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('position');
                $table->string('status');
                $table->string('token_hash', 64)->nullable()->index();
                $table->string('light_at_ask');
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->string('mail_error')->nullable();
                $table->timestamps();
                $table->unique(['cover_request_id', 'person_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cover_asks');
        Schema::dropIfExists('cover_requests');
    }
};
