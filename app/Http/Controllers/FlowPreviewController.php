<?php

namespace App\Http\Controllers;

use App\Models\FeePlan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 營運流程頁面預覽（假資料，供核對流暢，尚未接真實資料庫）。
 */
class FlowPreviewController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('FlowPreview/Index', [
            'steps' => [
                ['key' => 'students', 'title' => '學生建檔／學號', 'href' => '/flow-preview/students', 'desc' => '選填主檔、單筆或批次產學號'],
                ['key' => 'trial', 'title' => '試聽一週', 'href' => '/flow-preview/trial', 'desc' => '起迄自動＋6 天，可收下訂金'],
                ['key' => 'enrollment', 'title' => '報名計價', 'href' => '/flow-preview/enrollment', 'desc' => '選科、勾月份、繳別、折讓'],
                ['key' => 'counter', 'title' => '櫃台收費', 'href' => '/flow-preview/counter', 'desc' => '已繳未繳、收費單 PDF／圖'],
                ['key' => 'fee-plans', 'title' => '收費標準', 'href' => '/flow-preview/fee-plans', 'desc' => '年級×科目組價目'],
                ['key' => 'sessions', 'title' => '堂數／加課', 'href' => '/flow-preview/sessions', 'desc' => '科×月×堂、二三四加課'],
                ['key' => 'calendar', 'title' => '行事曆連假', 'href' => '/flow-preview/calendar', 'desc' => '國定假、暑休、招生季、確認日'],
                ['key' => 'short-courses', 'title' => '短期／特殊班', 'href' => '/flow-preview/short-courses', 'desc' => '一週班、批次、5/25 門檻'],
                ['key' => 'roster', 'title' => '確認名單', 'href' => '/flow-preview/roster', 'desc' => '7／8 月主管鎖定'],
                ['key' => 'revenue', 'title' => '營收報表', 'href' => '/flow-preview/revenue', 'desc' => '認列、科次圓餅'],
            ],
        ]);
    }

    public function students(): Response
    {
        return Inertia::render('FlowPreview/Students', [
            'nextCode' => '11501001',
            'batchPreview' => ['11501001', '11501002', '11501003'],
            'sampleStudents' => [
                ['code' => '11501001', 'name' => '王小明', 'grade' => '國一', 'status' => '試聽', 'school' => '龍美國中'],
                ['code' => '11501002', 'name' => '陳小華', 'grade' => '國一', 'status' => '在讀', 'school' => '龍美國中'],
                ['code' => '11402015', 'name' => '林大同', 'grade' => '國二', 'status' => '在讀', 'school' => '內埔國中'],
            ],
        ]);
    }

    public function trial(): Response
    {
        return Inertia::render('FlowPreview/Trial', [
            'students' => [
                ['id' => 1, 'code' => '11501001', 'name' => '王小明'],
                ['id' => 2, 'code' => '11501003', 'name' => '張試聽'],
            ],
            'subjects' => ['英文', '數學', '國文', '生物'],
        ]);
    }

    public function enrollment(): Response
    {
        return Inertia::render('FlowPreview/Enrollment', [
            'student' => [
                'code' => '11501001',
                'name' => '王小明',
                'grade' => '國一',
            ],
            'subjects' => [
                ['id' => 'en', 'name' => '英文', 'group' => 'core', 'list' => 3600, 'q_single' => 3200, 'q_double' => 3000, 'material' => 1200],
                ['id' => 'math', 'name' => '數學', 'group' => 'core', 'list' => 3600, 'q_single' => 3200, 'q_double' => 3000, 'material' => 1200],
                ['id' => 'zh', 'name' => '國文', 'group' => 'humanities', 'list' => 2000, 'q_single' => 1800, 'q_double' => 1800, 'material' => 900],
                ['id' => 'bio', 'name' => '生物', 'group' => 'humanities', 'list' => 2000, 'q_single' => 1800, 'q_double' => 1800, 'material' => 900],
            ],
            'months' => [
                ['y' => 2026, 'm' => 9],
                ['y' => 2026, 'm' => 10],
                ['y' => 2026, 'm' => 11],
                ['y' => 2026, 'm' => 12],
                ['y' => 2027, 'm' => 1],
                ['y' => 2027, 'm' => 2],
            ],
        ]);
    }

    public function counter(): Response
    {
        return Inertia::render('FlowPreview/Counter', [
            'student' => ['code' => '11501001', 'name' => '王小明'],
            'items' => [
                ['id' => 1, 'label' => '2026/09 英文學費（認列）', 'amount' => 3000, 'status' => 'unpaid', 'kind' => 'tuition'],
                ['id' => 2, 'label' => '2026/09 數學學費（認列）', 'amount' => 3000, 'status' => 'unpaid', 'kind' => 'tuition'],
                ['id' => 3, 'label' => '上學期教材費（英＋數）', 'amount' => 2400, 'status' => 'unpaid', 'kind' => 'material'],
                ['id' => 4, 'label' => '2026/08 訂金轉學費', 'amount' => 1000, 'status' => 'paid', 'kind' => 'deposit'],
            ],
        ]);
    }

    public function feePlans(): Response
    {
        $plans = FeePlan::query()
            ->with('gradeLevel:id,name')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($plans->isEmpty()) {
            return Inertia::render('FlowPreview/FeePlans', [
                'plans' => [
                    ['grade' => '國一', 'group' => '英／數', 'list' => '3,600／月', 'quarter' => '單 3,200／雙 3,000', 'material' => '1,200／學期', 'unit' => '月'],
                    ['grade' => '國一', 'group' => '國／生', 'list' => '2,000／月', 'quarter' => '1,800／月', 'material' => '900／學期', 'unit' => '月'],
                    ['grade' => '國二', 'group' => '英／數／理', 'list' => '3,600／月', 'quarter' => '單 3,200／雙 3,000', 'material' => '1,200／學期', 'unit' => '月'],
                    ['grade' => '國二', 'group' => '理化科研', 'list' => '4,400／４堂', 'quarter' => '3,800／４堂', 'material' => '1,500／科', 'unit' => '堂塊'],
                    ['grade' => '國二', 'group' => '國文', 'list' => '2,000／月', 'quarter' => '1,800／月', 'material' => '900／學期', 'unit' => '月'],
                    ['grade' => '國三', 'group' => '英／數／理', 'list' => '3,600／月', 'quarter' => '單 3,300／雙 3,100', 'material' => '1,500／學期', 'unit' => '月'],
                    ['grade' => '國三', 'group' => '理化科研', 'list' => '4,400／４堂', 'quarter' => '3,800／４堂', 'material' => '1,500／科', 'unit' => '堂塊'],
                    ['grade' => '國三', 'group' => '國／社', 'list' => '2,000／月', 'quarter' => '1,800／月', 'material' => '1,000／學期', 'unit' => '月'],
                ],
            ]);
        }

        return Inertia::render('FlowPreview/FeePlans', [
            'plans' => $plans->map(fn (FeePlan $plan): array => [
                'grade' => $plan->gradeLevel?->name ?? '—',
                'group' => $plan->group_name,
                'list' => $plan->listPriceLabel(),
                'quarter' => $plan->quarterLabel(),
                'material' => $plan->materialLabel(),
                'unit' => $plan->unit === 'session_block' ? '堂塊' : '月',
            ])->all(),
        ]);
    }

    public function sessions(): Response
    {
        return Inertia::render('FlowPreview/Sessions', [
            'className' => '國一狀元 A 班',
            'month' => '2026-09',
            'rows' => [
                ['subject' => '英文', 'planned' => 8, 'holiday' => 0, 'makeup' => 1, 'final' => 9],
                ['subject' => '數學', 'planned' => 8, 'holiday' => 1, 'makeup' => 0, 'final' => 7],
                ['subject' => '國文', 'planned' => 4, 'holiday' => 0, 'makeup' => 0, 'final' => 4],
                ['subject' => '生物', 'planned' => 4, 'holiday' => 0, 'makeup' => 0, 'final' => 4],
            ],
            'totalSessions' => 24,
            'totalSubjectSessions' => 4,
        ]);
    }

    public function calendar(): Response
    {
        return Inertia::render('FlowPreview/Calendar', [
            'events' => [
                ['type' => 'national_holiday', 'title' => '國慶連假', 'start' => '2026-10-09', 'end' => '2026-10-11'],
                ['type' => 'long_break', 'title' => '小暑休', 'start' => '2026-07-20', 'end' => '2026-07-25'],
                ['type' => 'exam_review', 'title' => '段考複習（兩週）', 'start' => '2026-10-20', 'end' => '2026-11-02'],
                ['type' => 'enrollment_season', 'title' => '招生季優惠', 'start' => '2026-05-01', 'end' => '2026-08-31'],
                ['type' => 'short_course_deadline', 'title' => '暑期班須顯示門檻', 'start' => '2026-05-25', 'end' => '2026-05-25'],
                ['type' => 'roster_confirm', 'title' => '確認名單（７月）', 'start' => '2026-07-01', 'end' => '2026-07-31'],
                ['type' => 'roster_confirm', 'title' => '確認名單（８月）', 'start' => '2026-08-01', 'end' => '2026-08-31'],
            ],
        ]);
    }

    public function shortCourses(): Response
    {
        return Inertia::render('FlowPreview/ShortCourses', [
            'deadline' => '2026-05-25',
            'courses' => [
                ['name' => '國二暑訓 A', 'grade' => '國二', 'start' => '2026-08-10', 'end' => '2026-08-14', 'fee' => 3600, 'visible' => true],
                ['name' => '國一暑期英文週', 'grade' => '國一', 'start' => '2026-07-27', 'end' => '2026-07-31', 'fee' => 3200, 'visible' => true],
                ['name' => '國三特訓週', 'grade' => '國三', 'start' => '2026-08-17', 'end' => '2026-08-21', 'fee' => 3600, 'visible' => true],
            ],
        ]);
    }

    public function roster(): Response
    {
        return Inertia::render('FlowPreview/Roster', [
            'period' => '2026-08',
            'rows' => [
                ['code' => '11501001', 'name' => '王小明', 'grade' => '國一', 'subjects' => '英、數', 'confirmed' => true],
                ['code' => '11501002', 'name' => '陳小華', 'grade' => '國一', 'subjects' => '英、數、國', 'confirmed' => false],
                ['code' => '11402015', 'name' => '林大同', 'grade' => '國二', 'subjects' => '英、數、理', 'confirmed' => true],
                ['code' => '11303008', 'name' => '黃國三', 'grade' => '國三', 'subjects' => '英、數、理（年繳）', 'confirmed' => false],
            ],
        ]);
    }

    public function revenue(): Response
    {
        return Inertia::render('FlowPreview/Revenue', [
            'month' => '2026-09',
            'recognized' => 428000,
            'collected' => 312000,
            'unpaid' => 86000,
            'monthly' => [
                ['month' => '7月', 'amount' => 380000],
                ['month' => '8月', 'amount' => 410000],
                ['month' => '9月', 'amount' => 428000],
                ['month' => '10月', 'amount' => 0],
            ],
            'pie' => [
                ['label' => '國一', 'value' => 180000, 'sessions' => 420],
                ['label' => '國二', 'value' => 168000, 'sessions' => 390],
                ['label' => '國三', 'value' => 80000, 'sessions' => 160],
            ],
            'note' => '季繳÷３認列；年繳攤 7月～隔年5月。本頁為示意數字。',
        ]);
    }
}
