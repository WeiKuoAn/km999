<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 上次在 unique 失敗後中斷：已有 course_category_id、已移除 category，補齊去重與 unique
        if (Schema::hasColumn('courses', 'course_category_id') && ! Schema::hasColumn('courses', 'category')) {
            $this->dedupeCourseCategoryNamePairs();
            if (! Schema::hasIndex('courses', ['course_category_id', 'name'], 'unique')) {
                Schema::table('courses', function (Blueprint $table) {
                    $table->unique(['course_category_id', 'name']);
                });
            }

            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedBigInteger('course_category_id')->nullable()->after('id');
        });

        if (Schema::hasColumn('courses', 'category')) {
            $names = DB::table('courses')->whereNotNull('category')->distinct()->pluck('category');

            foreach ($names as $name) {
                if ($name === '') {
                    continue;
                }

                $id = DB::table('course_categories')->where('name', $name)->value('id');

                if (! $id) {
                    $id = DB::table('course_categories')->insertGetId([
                        'name' => $name,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('courses')->where('category', $name)->update(['course_category_id' => $id]);
            }

            Schema::table('courses', function (Blueprint $table) {
                if (Schema::hasIndex('courses', ['category', 'name'])) {
                    $table->dropIndex(['category', 'name']);
                }
                $table->dropColumn('category');
            });
        }

        if (DB::table('courses')->whereNull('course_category_id')->exists()) {
            $miscId = DB::table('course_categories')->where('name', '未分類')->value('id');
            if (! $miscId) {
                $miscId = DB::table('course_categories')->insertGetId([
                    'name' => '未分類',
                    'sort_order' => 99,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('courses')->whereNull('course_category_id')->update(['course_category_id' => $miscId]);
        }

        DB::statement('ALTER TABLE courses MODIFY course_category_id BIGINT UNSIGNED NOT NULL');

        $this->dedupeCourseCategoryNamePairs();

        Schema::table('courses', function (Blueprint $table) {
            $hasFk = collect(Schema::getForeignKeys('courses'))
                ->contains(fn (array $fk) => $fk['columns'] === ['course_category_id']);

            if (! $hasFk) {
                $table->foreign('course_category_id')->references('id')->on('course_categories')->restrictOnDelete();
            }

            if (! Schema::hasIndex('courses', ['course_category_id', 'name'], 'unique')) {
                $table->unique(['course_category_id', 'name']);
            }
        });
    }

    /**
     * 同一類別下課程名稱重複時，保留 id 最小者，其餘名稱加上「 #id」以符合 unique。
     */
    private function dedupeCourseCategoryNamePairs(): void
    {
        $dupes = DB::select('
            SELECT course_category_id, name, GROUP_CONCAT(id ORDER BY id) AS ids
            FROM courses
            GROUP BY course_category_id, name
            HAVING COUNT(*) > 1
        ');

        foreach ($dupes as $row) {
            $ids = array_map('intval', explode(',', $row->ids));
            array_shift($ids);
            foreach ($ids as $id) {
                DB::table('courses')->where('id', $id)->update([
                    'name' => $row->name.' #'.$id,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['course_category_id']);
            $table->dropUnique(['course_category_id', 'name']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('category')->after('id');
        });

        $courses = DB::table('courses')->select(['id', 'course_category_id'])->get();

        foreach ($courses as $row) {
            $name = DB::table('course_categories')->where('id', $row->course_category_id)->value('name');
            DB::table('courses')->where('id', $row->id)->update(['category' => $name ?? '']);
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('course_category_id');
            $table->index(['category', 'name']);
        });
    }
};
