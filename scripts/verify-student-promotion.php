<?php

/**
 * 學生轉檔（升級）乾跑：檢查科目可自動延續、需手動、新年級有無收費設定／期別。
 * 不寫入資料庫。
 *
 * 用法：php scripts/verify-student-promotion.php
 */

use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Support\EnrollmentPricing;
use App\Support\PromotionCourses;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$passes = 0;
$failures = [];
$warnings = [];

$assert = function (bool $ok, string $label, ?string $detail = null) use (&$passes, &$failures): void {
    if ($ok) {
        $passes++;
        echo "  ✓ {$label}\n";

        return;
    }
    $failures[] = $label;
    echo '  ✗ '.$label.($detail ? " — {$detail}" : '')."\n";
};

$warn = function (string $label) use (&$warnings): void {
    $warnings[] = $label;
    echo "  ⚠ {$label}\n";
};

echo "=== 學生轉檔風險檢查 ===\n";

$g1 = GradeLevel::query()->where('code', 7)->first();
$g2 = GradeLevel::query()->where('code', 8)->first();
$g3 = GradeLevel::query()->where('code', 9)->first();

$assert($g1 && $g2 && $g3, '年級國一／二／三存在');

echo "\n[A] 各年級有收費設定的科目\n";
foreach ([['國一', $g1], ['國二', $g2], ['國三', $g3]] as [$label, $grade]) {
    $proxy = new Student([
        'grade_level_id' => $grade->id,
        'academic_year_id' => FeePlan::query()->whereNotNull('academic_year_id')->value('academic_year_id'),
    ]);
    $proxy->setRelation('gradeLevel', $grade);
    $proxy->load('academicYear');
    $subjects = collect(EnrollmentPricing::subjectsForStudent($proxy))
        ->filter(fn ($s) => ! empty($s['fee_plan_id']));
    $names = $subjects->map(fn ($s) => $s['name'].'(plan#'.$s['fee_plan_id'].'/'.$s['unit'].')')->implode('、');
    echo "  {$label}：".($names !== '' ? $names : '（無）')."\n";

    $missingPeriods = $subjects->filter(function ($s) {
        return ($s['unit'] ?? '') === 'custom_period' && ($s['periods'] ?? []) === [];
    });
    $assert(
        $missingPeriods->isEmpty(),
        "{$label} 套用 custom_period 的科目皆有期別",
        $missingPeriods->pluck('name')->implode('、') ?: null,
    );
}

echo "\n[B] 科目跨年級延續矩陣（同 course_id 在新年級是否仍有價目）\n";
$yearId = FeePlan::query()->whereNotNull('academic_year_id')->value('academic_year_id');

$subjectIdsFor = function (GradeLevel $grade) use ($yearId): array {
    $proxy = new Student([
        'grade_level_id' => $grade->id,
        'academic_year_id' => $yearId,
    ]);
    $proxy->setRelation('gradeLevel', $grade);
    $proxy->load('academicYear');

    return collect(EnrollmentPricing::subjectsForStudent($proxy))
        ->filter(fn ($s) => ! empty($s['fee_plan_id']))
        ->mapWithKeys(fn ($s) => [(int) $s['id'] => (string) $s['name']])
        ->all();
};

$s1 = $subjectIdsFor($g1);
$s2 = $subjectIdsFor($g2);
$s3 = $subjectIdsFor($g3);

$checkTransition = function (string $label, array $from, array $to) use ($assert, $warn): void {
    $auto = [];
    $manual = [];
    foreach ($from as $id => $name) {
        if (isset($to[$id])) {
            $auto[] = $name;
        } else {
            $manual[] = $name;
        }
    }
    echo "  {$label}\n";
    echo '    可自動延續：'.($auto !== [] ? implode('、', $auto) : '無')."\n";
    echo '    需手動（新年級無此科價目）：'.($manual !== [] ? implode('、', $manual) : '無')."\n";
    if ($manual !== []) {
        $warn("{$label}：".implode('、', $manual).' 無法自動延續（預期需手動選課，非 bug）');
    } else {
        $assert(true, "{$label}：全部可自動延續");
    }
};

$checkTransition('國一→國二', $s1, $s2);
$checkTransition('國二→國三', $s2, $s3);

echo "\n[C] 抽樣真實學生轉檔分析（國一→國二，最多 15 人）\n";
$students = Student::query()
    ->with(['academicYear', 'gradeLevel'])
    ->where('grade_level_id', $g1->id)
    ->where('status', 'active')
    ->orderBy('student_code')
    ->limit(15)
    ->get();

if ($students->isEmpty()) {
    $warn('沒有國一在學學生可抽樣');
} else {
    $modeCounts = ['auto' => 0, 'manual' => 0, 'no_courses' => 0];
    $blockedFreq = [];
    $quoteOk = 0;
    $quoteFail = [];

    foreach ($students as $student) {
        $analysis = PromotionCourses::analyze($student, $g2);
        if ($analysis['current_course_ids'] === []) {
            $modeCounts['no_courses']++;
        } elseif ($analysis['mode'] === 'auto') {
            $modeCounts['auto']++;
        } else {
            $modeCounts['manual']++;
        }
        foreach ($analysis['blocked_labels'] as $label) {
            $blockedFreq[$label] = ($blockedFreq[$label] ?? 0) + 1;
        }

        // 模擬轉檔後：用可延續＋若手動則用 target 前兩科，檢查能否 quote
        $afterIds = $analysis['mode'] === 'auto'
            ? $analysis['transferable_ids']
            : array_slice(array_map(fn ($s) => (int) $s['id'], $analysis['target_subjects']), 0, 2);

        if ($afterIds === []) {
            continue;
        }

        $proxy = $student->replicate();
        $proxy->id = $student->id;
        $proxy->grade_level_id = $g2->id;
        $proxy->setRelation('gradeLevel', $g2);
        $proxy->setRelation('academicYear', $student->academicYear);
        $proxy->intended_course_ids = $afterIds;

        $subjects = collect(EnrollmentPricing::subjectsForStudent($proxy))->keyBy('id');
        $missingPlan = collect($afterIds)->filter(fn ($id) => empty($subjects[$id]['fee_plan_id'] ?? null));
        if ($missingPlan->isNotEmpty()) {
            $quoteFail[] = $student->name.': 科目無價目 '.$missingPlan->implode(',');

            continue;
        }

        $sessions = [];
        foreach ($afterIds as $cid) {
            foreach ([7, 8, 9] as $m) {
                $sessions[] = ['date' => sprintf('2026-%02d-05', $m), 'course_id' => $cid];
            }
        }
        try {
            $quote = EnrollmentPricing::quote($proxy, $afterIds, 'quarterly', $sessions);
            if ($quote['lines'] === [] || $quote['tuition_total'] <= 0) {
                $quoteFail[] = $student->name.': tuition='.$quote['tuition_total'];
            } else {
                $quoteOk++;
            }
        } catch (Throwable $e) {
            $quoteFail[] = $student->name.': '.$e->getMessage();
        }
    }

    echo "  抽樣 {$students->count()} 人：自動 {$modeCounts['auto']}、需手動 {$modeCounts['manual']}、無進行中科目 {$modeCounts['no_courses']}\n";
    if ($blockedFreq !== []) {
        arsort($blockedFreq);
        echo '  最常被擋科目：'.collect($blockedFreq)->map(fn ($n, $k) => "{$k}×{$n}")->implode('、')."\n";
    }
    $assert($quoteFail === [], '轉檔後抽樣可正常試算學費', implode('；', $quoteFail) ?: null);
    echo "  成功試算 {$quoteOk} 筆\n";
}

echo "\n[D] 結構健檢\n";
$plansWithoutPeriods = FeePlan::query()
    ->where('unit', 'custom_period')
    ->where('is_active', true)
    ->whereDoesntHave('periods')
    ->pluck('group_name');
$assert($plansWithoutPeriods->isEmpty(), '沒有「自訂期別卻無期別列」的啟用價目', $plansWithoutPeriods->implode('、') ?: null);

$plansEmptyCoursePrice = FeePlan::query()
    ->with(['periods.coursePrices', 'courses'])
    ->where('unit', 'custom_period')
    ->where('is_active', true)
    ->get()
    ->filter(function (FeePlan $plan) {
        $courseIds = $plan->courses->pluck('id')->all();
        foreach ($plan->periods as $period) {
            $priced = $period->coursePrices->pluck('course_id')->all();
            foreach ($courseIds as $cid) {
                if (! in_array($cid, $priced, true)) {
                    return true;
                }
            }
        }

        return false;
    })
    ->map(fn (FeePlan $p) => '#'.$p->id.' '.$p->group_name);
$assert($plansEmptyCoursePrice->isEmpty(), '各期皆有各科月價', $plansEmptyCoursePrice->implode('、') ?: null);

$bioId = array_search('生物課', $s1, true);
if ($bioId !== false) {
    $assert(! isset($s2[$bioId]), '生物課僅國一有價目（國二無＝轉檔需手動，符合課表）');
}
$cnId = array_search('國文課', $s1, true);
if ($cnId !== false) {
    $assert(isset($s2[$cnId]), '國文課國二仍有價目，可自動延續');
}

echo "\n=== 結果：通過 {$passes}，失敗 ".count($failures).'，提醒 '.count($warnings)." ===\n";
if ($warnings !== []) {
    echo "提醒（多半是業務設計，非程式錯誤）：\n";
    foreach ($warnings as $w) {
        echo "  - {$w}\n";
    }
}
exit($failures === [] ? 0 : 1);
