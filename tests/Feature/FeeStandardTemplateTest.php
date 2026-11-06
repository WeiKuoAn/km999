<?php

use App\Models\AcademicYear;
use App\Models\FeeStandardTemplate;
use App\Models\FeeStandardTemplateExport;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function feeStandardCtx(): array
{
    $year = AcademicYear::query()->create([
        'year_code' => '115',
        'name' => '115學年度',
        'is_current' => true,
    ]);
    $grade = GradeLevel::query()->create([
        'name' => '國二',
        'code' => 8,
        'is_active' => true,
        'sort_order' => 8,
    ]);

    return compact('year', 'grade');
}

function feeStandardAdmin(): User
{
    // 不用 factory，避免本機缺 intl 時 Faker 失敗
    return User::query()->create([
        'name' => '管理員',
        'email' => 'fee-standard-'.uniqid('', true).'@example.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
    ]);
}

function feeStandardPayload(AcademicYear $year, GradeLevel $grade, array $overrides = []): array
{
    return array_merge([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'title' => '秋季班收費標準',
        'deadline_label' => '6/20',
        'footer_note' => '費用上有任何疑問，歡迎來電 07-5524889，感謝家長配合。',
        'is_active' => true,
        'sort_order' => 0,
        'items' => [
            [
                'label' => '理化科研班首期 7-10 月優惠',
                'detail' => '訂金 500 元(已繳) + 學費 12000 元 (含教材) = 11500 元',
                'sort_order' => 0,
            ],
            [
                'label' => '暑訓 8/7-8/12',
                'detail' => '班內生優惠價 1000 元',
                'sort_order' => 1,
            ],
        ],
    ], $overrides);
}

test('admin can create fee standard template with items', function () {
    ['year' => $year, 'grade' => $grade] = feeStandardCtx();

    $this->actingAs(feeStandardAdmin())
        ->post(route('fee-standard-templates.store'), feeStandardPayload($year, $grade))
        ->assertRedirect(route('fee-standard-templates.index'));

    $template = FeeStandardTemplate::query()->with('items')->sole();
    expect($template->title)->toBe('秋季班收費標準')
        ->and($template->grade_level_id)->toBe($grade->id)
        ->and($template->items)->toHaveCount(2)
        ->and($template->items->first()->label)->toContain('理化');
});

test('admin can create fee standard template with yellow header tables', function () {
    ['year' => $year, 'grade' => $grade] = feeStandardCtx();

    $this->actingAs(feeStandardAdmin())
        ->post(route('fee-standard-templates.store'), feeStandardPayload($year, $grade, [
            'items' => [
                [
                    'kind' => 'table',
                    'label' => '英文+數學雙科',
                    'heading_color' => '#1d4ed8',
                    'table_headers' => ['繳費月份', '英數雙科收費標準', '教材費/學期'],
                    'table_rows' => [
                        ['7-11月(5個月)', '3600元*5個月*2科=36000元', '1200元*2科=2400元'],
                    ],
                    'sort_order' => 0,
                ],
                [
                    'kind' => 'check',
                    'label' => '暑訓 8/7-8/12',
                    'detail' => '班內生優惠價 1000 元',
                    'sort_order' => 1,
                ],
            ],
        ]))
        ->assertRedirect(route('fee-standard-templates.index'));

    $template = FeeStandardTemplate::query()->with('items')->sole();
    $table = $template->items->firstWhere('kind', 'table');
    expect($table)->not->toBeNull()
        ->and($table->label)->toBe('英文+數學雙科')
        ->and($table->heading_color)->toBe('#1d4ed8')
        ->and($table->table_headers)->toHaveCount(3)
        ->and($table->table_rows[0][1])->toContain('36000')
        ->and($template->items)->toHaveCount(2);

    $exported = $table->toExportArray();
    expect($exported['kind'])->toBe('table')
        ->and($exported['table_headers'][0])->toBe('繳費月份');
});

test('template requires at least one item', function () {
    ['year' => $year, 'grade' => $grade] = feeStandardCtx();

    $this->actingAs(feeStandardAdmin())
        ->post(route('fee-standard-templates.store'), feeStandardPayload($year, $grade, [
            'items' => [],
        ]))
        ->assertSessionHasErrors('items');
});

test('admin can duplicate template', function () {
    ['year' => $year, 'grade' => $grade] = feeStandardCtx();
    $admin = feeStandardAdmin();

    $this->actingAs($admin)
        ->post(route('fee-standard-templates.store'), feeStandardPayload($year, $grade));

    $original = FeeStandardTemplate::query()->sole();

    $this->actingAs($admin)
        ->post(route('fee-standard-templates.duplicate', $original))
        ->assertRedirect();

    $copy = FeeStandardTemplate::query()->whereKeyNot($original->id)->sole();
    expect($copy->title)->toContain('複製')
        ->and($copy->is_active)->toBeFalse()
        ->and($copy->items()->count())->toBe(2);
});

test('grade pdf exports active students two per sheet', function () {
    ['year' => $year, 'grade' => $grade] = feeStandardCtx();
    $admin = feeStandardAdmin();

    $this->actingAs($admin)
        ->post(route('fee-standard-templates.store'), feeStandardPayload($year, $grade));

    $template = FeeStandardTemplate::query()->sole();

    Student::query()->create([
        'name' => '郭睿恩',
        'student_code' => 'A001',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);
    Student::query()->create([
        'name' => '停用生',
        'student_code' => 'A002',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'graduated',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('fee-standard-templates.pdf', $template).'?scope=grade');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect(strlen($response->getContent()))->toBeGreaterThan(500);
});

test('student-scoped pdf requires student ids', function () {
    ['year' => $year, 'grade' => $grade] = feeStandardCtx();
    $admin = feeStandardAdmin();

    $this->actingAs($admin)
        ->post(route('fee-standard-templates.store'), feeStandardPayload($year, $grade));

    $template = FeeStandardTemplate::query()->sole();

    $this->actingAs($admin)
        ->get(route('fee-standard-templates.pdf', $template).'?scope=students')
        ->assertSessionHasErrors('student_ids');
});

test('pdf export records students and hides them from list', function () {
    ['year' => $year, 'grade' => $grade] = feeStandardCtx();
    $admin = feeStandardAdmin();

    $this->actingAs($admin)
        ->post(route('fee-standard-templates.store'), feeStandardPayload($year, $grade));

    $template = FeeStandardTemplate::query()->sole();
    $student = Student::query()->create([
        'name' => '葉千孜',
        'student_code' => 'B001',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('fee-standard-templates.pdf', $template).'?scope=grade')
        ->assertOk();

    expect(
        FeeStandardTemplateExport::query()
            ->where('fee_standard_template_id', $template->id)
            ->where('student_id', $student->id)
            ->exists()
    )->toBeTrue();

    $hidden = $this->actingAs($admin)
        ->getJson(route('fee-standard-templates.students', $template));
    $hidden->assertOk();
    expect(collect($hidden->json('students'))->pluck('id'))->not->toContain($student->id)
        ->and($hidden->json('exported_count'))->toBe(1);

    $shown = $this->actingAs($admin)
        ->getJson(route('fee-standard-templates.students', $template).'?include_exported=1');
    $shown->assertOk();
    expect(collect($shown->json('students'))->pluck('id'))->toContain($student->id);
});
