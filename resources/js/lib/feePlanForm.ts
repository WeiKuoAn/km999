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
    min_courses: number;
    price: number | '';
};

/** 表單上的收費方式（存檔時 quarter／other → session_block） */
export type FeePlanBillingKind = 'month' | 'quarter' | 'other';

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
    /** 一個月原價（按季／其他存檔時會 × 月數） */
    list_price: number;
    /** 一個月優惠價（按季／其他存檔時會 × 月數；留白＝用原價） */
    quarter_price: number | '';
    material_fee: number;
    material_unit: 'term' | 'subject' | 'class_day';
    sort_order: number;
    is_active: boolean;
    bundles: FeePlanBundleRow[];
};

const emptyToNull = (v: number | string | undefined) =>
    v === '' || v === undefined || Number.isNaN(Number(v)) ? null : Number(v);

export function billingKindFromPlan(
    unit: 'month' | 'session_block',
    sessionBlockSize: number | null,
): FeePlanBillingKind {
    if (unit !== 'session_block') {
        return 'month';
    }

    return (sessionBlockSize ?? 3) === 3 ? 'quarter' : 'other';
}

/** 後端存的是一期總額時，轉回表單用的「一個月」 */
export function periodToMonthly(
    periodPrice: number,
    kind: FeePlanBillingKind,
    months: number,
): number {
    if (kind === 'month' || months <= 1) {
        return periodPrice;
    }

    return Math.round(periodPrice / months);
}

export function periodMonths(kind: FeePlanBillingKind, blockSize: number | ''): number {
    if (kind === 'month') {
        return 1;
    }
    if (kind === 'quarter') {
        return 3;
    }

    return Math.max(1, Number(blockSize) || 4);
}

export function feePlanPayload(data: FeePlanFormData) {
    const months = periodMonths(data.billing_kind, data.session_block_size);
    const monthlyList = Number(data.list_price || 0);
    const monthlyQuarter = emptyToNull(data.quarter_price);

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
        unit: data.billing_kind === 'month' ? 'month' : 'session_block',
        session_block_size:
            data.billing_kind === 'month' ? null : months,
        list_price: monthlyList * months,
        quarter_price:
            monthlyQuarter === null ? null : monthlyQuarter * months,
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
            min_courses: Number(bundle.min_courses || 2),
            price: bundle.price === '' ? null : Number(bundle.price),
        })),
    };
}
