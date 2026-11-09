<?php

use App\Models\Student;
use App\Support\EnrollmentPricing;
use App\Support\MaterialHalfYear;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('auto material charges only when january or july is in billing months', function () {
    $student = Student::query()->create([
        'student_code' => 'MAT001',
        'name' => '教材閘道',
        'status' => 'active',
    ]);

    $subjectsById = [
        10 => [
            'id' => 10,
            'name' => '理化課',
            'material' => 2400,
            'material_unit' => 'term',
        ],
    ];

    // 只有 12、2 月 → 不應自動收
    $none = MaterialHalfYear::autoChargesForMonths(
        $student,
        [10],
        [['y' => 2026, 'm' => 12], ['y' => 2027, 'm' => 2]],
        $subjectsById,
    );
    expect($none)->toBe([]);

    // 含 1 月 → 收 2027 上半年
    $withJan = MaterialHalfYear::autoChargesForMonths(
        $student,
        [10],
        [['y' => 2026, 'm' => 12], ['y' => 2027, 'm' => 1]],
        $subjectsById,
    );
    expect($withJan)->toHaveCount(1)
        ->and($withJan[0]['period_year'])->toBe(2027)
        ->and($withJan[0]['period_half'])->toBe('H1')
        ->and($withJan[0]['amount'])->toBe(1200);

    // 含 7 月 → 收下半年
    $withJul = MaterialHalfYear::autoChargesForMonths(
        $student,
        [10],
        [['y' => 2026, 'm' => 7], ['y' => 2026, 'm' => 8]],
        $subjectsById,
    );
    expect($withJul)->toHaveCount(1)
        ->and($withJul[0]['period_half'])->toBe('H2')
        ->and($withJul[0]['period_year'])->toBe(2026);
});

test('quote hangs material amount only on january or july', function () {
    $ref = new ReflectionClass(EnrollmentPricing::class);
    $method = $ref->getMethod('materialForHalfCharges');
    $method->setAccessible(true);

    $subject = ['material' => 2400, 'name' => '理化課'];
    $charges = [[
        'period_year' => 2027,
        'period_half' => 'H1',
        'amount' => 1200,
    ]];

    // 有 1 月 → 掛在 1 月
    [$total, $byMonth] = $method->invoke(
        null,
        $subject,
        [['y' => 2026, 'm' => 12], ['y' => 2027, 'm' => 1], ['y' => 2027, 'm' => 2]],
        $charges,
    );
    expect($total)->toBe(1200)
        ->and($byMonth)->toHaveKey('2027-1')
        ->and($byMonth)->not->toHaveKey('2026-12')
        ->and($byMonth)->not->toHaveKey('2027-2');

    // 沒有 1 月 → 不收
    [$totalNone, $byMonthNone] = $method->invoke(
        null,
        $subject,
        [['y' => 2026, 'm' => 12], ['y' => 2027, 'm' => 2]],
        $charges,
    );
    expect($totalNone)->toBe(0)->and($byMonthNone)->toBe([]);
});
