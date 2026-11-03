<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Support\PricingGroup;
use Illuminate\Database\Seeder;

/**
 * 國三年繳方案（建立在收費設定）：全科／全科科研／國社。
 * 可與期別設定共用科目；收款時科目剛好符合才套用總額。
 */
class Grade9AnnualPackageSeeder extends Seeder
{
    public function run(): void
    {
        $yearId = AcademicYear::query()->where('is_current', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('year_code')->value('id');

        $grade = GradeLevel::query()
            ->where('name', '國三')
            ->orWhere('code', 9)
            ->first();

        if ($grade === null) {
            $this->command?->warn('找不到國三年級，略過年繳方案 seed。');

            return;
        }

        $courseIds = Course::query()
            ->where('status', 'active')
            ->get(['id', 'name'])
            ->keyBy(fn (Course $c): string => $c->name);

        $resolve = function (string ...$aliases) use ($courseIds): ?Course {
            foreach ($aliases as $alias) {
                if ($courseIds->has($alias)) {
                    return $courseIds->get($alias);
                }
            }
            foreach ($aliases as $alias) {
                $hit = $courseIds->first(
                    fn (Course $c): bool => str_contains($c->name, $alias)
                );
                if ($hit !== null) {
                    return $hit;
                }
            }

            return null;
        };

        $chinese = $resolve('國文課', '國文');
        $english = $resolve('英文課', '英文');
        $math = $resolve('數學課', '數學');
        $science = $resolve('理化課', '理化');
        $scienceResearch = $resolve('理化科研班', '理化科研', '科研');
        $social = $resolve('社會課', '社會');

        foreach (
            [
                '國文課' => $chinese,
                '英文課' => $english,
                '數學課' => $math,
                '理化課' => $science,
                '理化科研' => $scienceResearch,
                '社會課' => $social,
            ] as $label => $course
        ) {
            if ($course === null) {
                $this->command?->warn("找不到課目「{$label}」，略過年繳方案 seed。");

                return;
            }
        }

        $packages = [
            [
                'group_name' => '全科年繳',
                'course_ids' => [$chinese->id, $english->id, $math->id, $science->id, $social->id],
                'package_total' => 120_000,
                'annual_start_month' => 7,
                'annual_end_month' => 12,
                'sort_order' => 90,
            ],
            [
                'group_name' => '全科科研年繳',
                'course_ids' => [$chinese->id, $english->id, $math->id, $scienceResearch->id, $social->id],
                'package_total' => 136_000,
                'annual_start_month' => 7,
                'annual_end_month' => 12,
                'sort_order' => 91,
            ],
            [
                'group_name' => '國社年繳',
                'course_ids' => [$chinese->id, $social->id],
                'package_total' => 37_000,
                'annual_start_month' => 7,
                'annual_end_month' => 6,
                'sort_order' => 92,
            ],
        ];

        foreach ($packages as $row) {
            /** @var list<int> $ids */
            $ids = array_map('intval', $row['course_ids']);

            $plan = FeePlan::query()->firstOrNew([
                'academic_year_id' => $yearId,
                'grade_level_id' => $grade->id,
                'group_name' => $row['group_name'],
                'plan_kind' => 'annual_package',
            ]);

            $plan->fill([
                'pricing_group' => PricingGroup::CORE,
                'unit' => 'custom_period',
                'plan_kind' => 'annual_package',
                'per_course_periods' => false,
                'allow_annual' => true,
                'annual_start_month' => $row['annual_start_month'],
                'annual_end_month' => $row['annual_end_month'],
                'package_total' => $row['package_total'],
                'list_price' => 0,
                'material_fee' => 0,
                'material_unit' => 'term',
                'sort_order' => $row['sort_order'],
                'is_active' => true,
            ]);
            $plan->save();
            $plan->courses()->sync($ids);
            $plan->periods()->delete();
            $plan->annualPrices()->delete();

            $this->command?->info("已寫入年繳方案：{$row['group_name']}（\${$row['package_total']}）");
        }

        // 期別設定若另開「開放年繳」會與方案搶匹配；國三期別建議關閉 allow_annual
        $disabled = FeePlan::query()
            ->where('grade_level_id', $grade->id)
            ->where(function ($q): void {
                $q->where('plan_kind', 'period')
                    ->orWhereNull('plan_kind')
                    ->orWhere('plan_kind', '');
            })
            ->when($yearId !== null, fn ($q) => $q->where('academic_year_id', $yearId))
            ->where('allow_annual', true)
            ->update([
                'allow_annual' => false,
                'annual_start_month' => null,
                'annual_end_month' => null,
            ]);

        if ($disabled > 0) {
            $this->command?->info("已關閉 {$disabled} 筆國三期別設定的「開放年繳」，改走年繳方案。");
        }
    }
}
