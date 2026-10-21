export type FeePlanCourseOption = {
    id: number;
    name: string;
    color: string | null;
    status: 'active' | 'paused';
    pricing_group: string | null;
    pricing_group_label: string;
    category_id: number;
    category_name: string;
    levels: string[];
};

export type FeePlanBundleRow = {
    id: number | null;
    name: string;
    course_ids: number[];
    /** 各科每月優惠價；留白＝該科不優惠（收原價） */
    course_prices: Record<number, number | ''>;
    min_courses: number;
};

export type FeePlanPeriodRow = {
    start_month: number | '';
    end_month: number | '';
    total_price: number | '';
};

/** 表單上的收費方式（存檔時 quarter／other → session_block） */
export type FeePlanBillingKind = 'month' | 'quarter' | 'other' | 'custom';

export type FeePlanFormData = {
    academic_year_id: string;
    grade_level_id: string;
    course_ids: number[];
    group_name: string;
    pricing_group: string;
    /** UI：按月／按季／其他 */
    billing_kind: FeePlanBillingKind;
    /** 其他：一期幾個月份；按季固定 3 */
    session_block_size: number | '';
    /** 一個月原價（資料庫也存月價；按期收款時再 × 月數） */
    list_price: number;
    /** 一個月優惠價（留白＝用原價） */
    quarter_price: number | '';
    material_fee: number;
    material_unit: 'term' | 'subject' | 'class_day';
    sort_order: number;
    is_active: boolean;
    periods: FeePlanPeriodRow[];
    bundles: FeePlanBundleRow[];
};

const emptyToNull = (v: number | string | undefined) =>
    v === '' || v === undefined || Number.isNaN(Number(v)) ? null : Number(v);

export function billingKindFromPlan(
    unit: 'month' | 'session_block' | 'custom_period',
    sessionBlockSize: number | null,
): FeePlanBillingKind {
    if (unit === 'custom_period') {
        return 'custom';
    }

    if (unit !== 'session_block') {
        return 'month';
    }

    return (sessionBlockSize ?? 3) === 3 ? 'quarter' : 'other';
}

export function periodMonths(kind: FeePlanBillingKind, blockSize: number | ''): number {
    if (kind === 'month') {
        return 1;
    }
    if (kind === 'quarter') {
        return 3;
    }
    if (kind === 'custom') {
        return 0;
    }

    return Math.max(1, Number(blockSize) || 4);
}

export function emptyCoursePrices(courseIds: number[]): Record<number, number | ''> {
    return Object.fromEntries(courseIds.map((id) => [id, '' as const]));
}

export function feePlanPayload(data: FeePlanFormData) {
    const months = periodMonths(data.billing_kind, data.session_block_size);

    return {
        academic_year_id:
            data.academic_year_id === '' ? null : Number(data.academic_year_id),
        grade_level_id:
            data.grade_level_id === '' || data.grade_level_id === 'all'
                ? null
                : Number(data.grade_level_id),
        course_ids: data.course_ids.map(Number),
        group_name: data.group_name,
        pricing_group: data.pricing_group,
        unit:
            data.billing_kind === 'custom'
                ? 'custom_period'
                : data.billing_kind === 'month'
                  ? 'month'
                  : 'session_block',
        session_block_size:
            data.billing_kind === 'month' || data.billing_kind === 'custom'
                ? null
                : months,
        list_price: Number(data.list_price || 0),
        quarter_price:
            data.billing_kind === 'custom' ? null : emptyToNull(data.quarter_price),
        periods:
            data.billing_kind === 'custom'
                ? data.periods.map((period) => ({
                      start_month:
                          period.start_month === '' ? '' : Number(period.start_month),
                      end_month: period.end_month === '' ? '' : Number(period.end_month),
                      total_price:
                          period.total_price === '' ? '' : Number(period.total_price),
                  }))
                : [],
        quarter_single_price: null,
        quarter_double_price: null,
        material_fee: Number(data.material_fee || 0),
        material_unit: data.material_unit,
        sort_order: Number(data.sort_order || 0),
        is_active: data.is_active,
        bundles: data.bundles.map((bundle) => ({
            id: bundle.id,
            name: bundle.name,
            course_ids: bundle.course_ids.map(Number),
            course_prices: Object.fromEntries(
                bundle.course_ids.map((id) => [
                    id,
                    emptyToNull(bundle.course_prices[id]),
                ]),
            ),
            min_courses: Number(bundle.min_courses || 2),
        })),
    };
}
