<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_roles')) {
            Schema::create('staff_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id');
                $table->string('slug', 40);
                $table->string('name', 40);
                $table->string('kind', 20);
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();
                $table->unique(['school_id', 'slug']);
            });
        }

        if (Schema::hasTable('schools')) {
            foreach (\Illuminate\Support\Facades\DB::table('schools')->pluck('id') as $schoolId) {
                \App\Hr\Roles::ensure((int) $schoolId);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_roles');
    }
};
