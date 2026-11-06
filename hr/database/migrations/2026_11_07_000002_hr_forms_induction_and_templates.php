<?php

use App\Hr\ContractPack;
use App\Hr\InductionPack;
use App\Models\Booking;
use App\Models\InductionModule;
use App\Models\Person;
use App\Models\School;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('people')) {
            Schema::table('people', function (Blueprint $table) {
                if (! Schema::hasColumn('people', 'available_days')) {
                    $table->json('available_days')->nullable();
                }
                if (! Schema::hasColumn('people', 'earliest_start')) {
                    $table->string('earliest_start')->nullable();
                }
                if (! Schema::hasColumn('people', 'neighbourhoods')) {
                    $table->json('neighbourhoods')->nullable();
                }
            });
        }

        if (! Schema::hasTable('induction_modules') || ! Schema::hasTable('schools')) {
            return;
        }

        InductionModule::query()->each(function (InductionModule $module) {
            $body = InductionPack::body($module->title);
            if ($body !== $module->title && ($module->body === $module->title || strlen((string) $module->body) < 80)) {
                $module->update(['body' => $body]);
            }
        });

        School::query()->each(function (School $school) {
            ContractPack::ensure($school);
        });

        if (! Schema::hasTable('bookings')) {
            return;
        }

        $sam = Person::query()->where('email', 'sam.green@example.com')->first();
        if ($sam && $sam->available_days === null) {
            $sam->update([
                'available_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'earliest_start' => '8:30',
                'neighbourhoods' => ['Ngulingah'],
            ]);
        }
        if ($sam) {
            $today = Booking::query()->where('person_id', $sam->id)->whereDate('booked_on', now()->toDateString())->first();
            if (! $today) {
                Booking::create([
                    'school_id' => $sam->school_id,
                    'person_id' => $sam->id,
                    'booked_on' => now()->toDateString(),
                    'class_label' => 'S3 sample',
                    'plan_url' => 'https://portal.living.school/',
                    'program_source' => 'wordpress',
                    'external_program_id' => 'sample-s3',
                    'light_at_booking' => 'green',
                ]);
            } elseif (! $today->plan_url) {
                $today->update([
                    'plan_url' => 'https://portal.living.school/',
                    'external_program_id' => $today->external_program_id ?: 'sample-s3',
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('people')) {
            return;
        }

        Schema::table('people', function (Blueprint $table) {
            foreach (['neighbourhoods', 'earliest_start', 'available_days'] as $column) {
                if (Schema::hasColumn('people', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
