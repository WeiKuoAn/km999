<?php

use App\Support\CustomBillingPeriod;

test('month count for contiguous and wrap-around periods', function () {
    expect(CustomBillingPeriod::monthCount(7, 9))->toBe(3)
        ->and(CustomBillingPeriod::monthCount(7, 8))->toBe(2)
        ->and(CustomBillingPeriod::monthCount(9, 12))->toBe(4)
        ->and(CustomBillingPeriod::monthCount(1, 1))->toBe(1)
        ->and(CustomBillingPeriod::monthCount(11, 2))->toBe(4)
        ->and(CustomBillingPeriod::monthNumbers(10, 12))->toBe([10, 11, 12]);
});

test('two-month and four-month period totals use monthly times months', function () {
    expect(CustomBillingPeriod::periodTotalForSubjectCount([
        'total_price' => 1800,
        'month_count' => 2,
    ], 1))->toBe(3600)
        ->and(CustomBillingPeriod::periodTotalForSubjectCount([
            'total_price' => 1800,
            'double_price' => 1600,
            'month_count' => 4,
        ], 1))->toBe(7200)
        ->and(CustomBillingPeriod::periodTotalForSubjectCount([
            'total_price' => 1800,
            'double_price' => 1600,
            'month_count' => 4,
        ], 2))->toBe(6400);
});

test('price for subject count uses single double and multi monthly rates', function () {
    $period = [
        'total_price' => 2000,
        'double_price' => 1800,
        'triple_price' => 1600,
        'month_count' => 3,
    ];

    expect(CustomBillingPeriod::priceForSubjectCount($period, 1))->toBe(2000)
        ->and(CustomBillingPeriod::priceForSubjectCount($period, 2))->toBe(1800)
        ->and(CustomBillingPeriod::priceForSubjectCount($period, 3))->toBe(1600)
        ->and(CustomBillingPeriod::periodTotalForSubjectCount($period, 1))->toBe(6000)
        ->and(CustomBillingPeriod::periodTotalForSubjectCount($period, 2))->toBe(5400);
});

test('blank double and multi fall back to single monthly', function () {
    $period = [
        'total_price' => 2000,
        'double_price' => null,
        'triple_price' => null,
        'month_count' => 3,
    ];

    expect(CustomBillingPeriod::priceForSubjectCount($period, 2))->toBe(2000)
        ->and(CustomBillingPeriod::periodTotalForSubjectCount($period, 3))->toBe(6000);
});
