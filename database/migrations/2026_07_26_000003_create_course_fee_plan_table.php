<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_fee_plan', function (Blueprint $table) {
            $table->foreignId('fee_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            $table->unique(['fee_plan_id', 'course_id']);
            $table->index('course_id');
        });

        DB::table('fee_plans')
            ->select(['id', 'pricing_group'])
            ->orderBy('id')
            ->each(function (object $plan): void {
                $courseIds = DB::table('courses')
                    ->where('pricing_group', $plan->pricing_group)
                    ->pluck('id');

                if ($courseIds->isEmpty()) {
                    return;
                }

                DB::table('course_fee_plan')->insertOrIgnore(
                    $courseIds
                        ->map(fn (int $courseId): array => [
                            'fee_plan_id' => $plan->id,
                            'course_id' => $courseId,
                        ])
                        ->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_fee_plan');
    }
};
