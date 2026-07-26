<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALLOWED = ['國小', '國中', '高中'];

    public function up(): void
    {
        if (! Schema::hasColumn('students', 'school_segment')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('school_segment')->nullable()->after('note');
            });
        }

        if (Schema::hasColumn('students', 'price_tier_1')) {
            $rows = DB::table('students')->select('id', 'price_tier_1', 'price_tier_2', 'price_tier_3')->get();
            foreach ($rows as $row) {
                $seg = null;
                foreach ([$row->price_tier_1, $row->price_tier_2, $row->price_tier_3] as $t) {
                    if ($t !== null && $t !== '' && in_array($t, self::ALLOWED, true)) {
                        $seg = $t;
                        break;
                    }
                }
                DB::table('students')->where('id', $row->id)->update(['school_segment' => $seg]);
            }

            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn(['price_tier_1', 'price_tier_2', 'price_tier_3']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'school_segment')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('price_tier_1')->nullable()->after('note');
                $table->string('price_tier_2')->nullable()->after('price_tier_1');
                $table->string('price_tier_3')->nullable()->after('price_tier_2');
            });

            foreach (DB::table('students')->whereNotNull('school_segment')->orderBy('id')->cursor() as $row) {
                DB::table('students')->where('id', $row->id)->update([
                    'price_tier_1' => $row->school_segment,
                    'price_tier_2' => null,
                    'price_tier_3' => null,
                ]);
            }

            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('school_segment');
            });
        }
    }
};
