<?php

use App\Hr\ContractPack;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_templates') && ! Schema::hasColumn('contract_templates', 'sort')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->unsignedInteger('sort')->default(0);
            });
        }

        if (! Schema::hasTable('contract_templates') || ! Schema::hasColumn('contract_templates', 'sort')) {
            return;
        }

        $preferred = array_column(ContractPack::templates(), 'key');
        $schoolIds = DB::table('contract_templates')->distinct()->pluck('school_id');
        foreach ($schoolIds as $schoolId) {
            $keys = DB::table('contract_templates')->where('school_id', $schoolId)->distinct()->pluck('key');
            $ordered = collect($preferred)
                ->filter(fn ($key) => $keys->contains($key))
                ->merge($keys->reject(fn ($key) => in_array($key, $preferred, true))->sort()->values());
            $position = 1;
            foreach ($ordered as $key) {
                DB::table('contract_templates')
                    ->where('school_id', $schoolId)
                    ->where('key', $key)
                    ->where('sort', 0)
                    ->update(['sort' => $position]);
                $position++;
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contract_templates') && Schema::hasColumn('contract_templates', 'sort')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->dropColumn('sort');
            });
        }
    }
};
