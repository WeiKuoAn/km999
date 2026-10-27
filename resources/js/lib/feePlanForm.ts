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

/** 某一期裡某一科的價格（填每月單價；一期應收＝月價×期月數） */
export type FeePlanPeriodCoursePrice = {
    /** 該科單科每月價 */
    total_price: number | '';
    /** 雙科時該科每月價；留白＝用單科月價 */
    double_price: number | '';
    /** 三科時該科每月價；留白＝用單科／雙科月價 */
    triple_price: number | '';
};

export type FeePlanPeriodRow = {
    start_month: number | '';
    end_month: number | '';
    /** 依勾選科目分別設定金額 */
    course_prices: Record<number, FeePlanPeriodCoursePrice>;
};

export function emptyPeriodCoursePrice(): FeePlanPeriodCoursePrice {
    return { total_price: '', double_price: '', triple_price: '' };
}

export function emptyPeriodCoursePrices(
    courseIds: number[],
): Record<number, FeePlanPeriodCoursePrice> {
    return Object.fromEntries(
        courseIds.map((id) => [id, emptyPeriodCoursePrice()]),
    );
}

/** 表單上的收費方式（存檔時 quarter／other → session_block） */
export type FeePlanBillingKind = 'month' | 'quarter' | 'other' | 'custom';

export type FeePlanFormData = {
    academic_year_id: string;
    grade_level_id: string;
    course_ids: number[];
    group_name: string;
    pricing_group: string;
    /** UI：按月／按季／其他／自訂期別 */
    billing_kind: FeePlanBillingKind;
    /** 其他：一期幾個月份；按季固定 3 */
    session_block_size: number | '';
    /** 已停用參考原價；送出時寫 0 相容舊欄位 */
    list_price: number;
    /** 舊版單科月價（自訂期別改走 periods） */
    quarter_price: number | '';
    /** 按月／按季：雙科每月價（留白＝不另設雙科價） */
    double_monthly: number | '';
    /** 按月／按季：三科每月價 */
    triple_monthly: number | '';
    material_fee: number;
    material_unit: 'term' | 'subject' | 'class_day';
    sort_order: number;
    is_active: boolean;
    periods: FeePlanPeriodRow[];
    /** 僅編輯時載入舊資料；存檔改由 double/triple_monthly 產生 */
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

/** 從舊的組合優惠列推回雙科／三科每月價（各科同價時） */
export function monthlyTiersFromBundles(
    bundles: Array<{
        min_courses: number;
        course_prices: Record<number | string, number | null | ''>;
    }>,
): { double_monthly: number | ''; triple_monthly: number | '' } {
    let double_monthly: number | '' = '';
    let triple_monthly: number | '' = '';

    for (const bundle of bundles) {
        const priced = Object.values(bundle.course_prices)
            .map((v) => (v === '' || v === null || v === undefined ? null : Number(v)))
            .filter((v): v is number => v !== null && !Number.isNaN(v));
        if (priced.length === 0) {
            continue;
        }
        const same = priced.every((p) => p === priced[0]) ? priced[0] : null;
        if (same === null) {
            continue;
        }
        if (bundle.min_courses >= 3) {
            triple_monthly = same;
        } else if (bundle.min_courses >= 2) {
            double_monthly = same;
        }
    }

    return { double_monthly, triple_monthly };
}

function synthesizeBundles(data: FeePlanFormData): FeePlanBundleRow[] {
    const courseIds = data.course_ids.map(Number);
    if (courseIds.length < 2 || data.billing_kind === 'custom') {
        return [];
    }

    const out: FeePlanBundleRow[] = [];
    const double = emptyToNull(data.double_monthly);
    const triple = emptyToNull(data.triple_monthly);

    if (double !== null) {
        out.push({
            id: null,
            name: '',
            course_ids: courseIds,
            course_prices: Object.fromEntries(courseIds.map((id) => [id, double])),
            min_courses: 2,
        });
    }
    if (triple !== null && courseIds.length >= 3) {
        out.push({
            id: null,
            name: '',
            course_ids: courseIds,
            course_prices: Object.fromEntries(courseIds.map((id) => [id, triple])),
            min_courses: 3,
        });
    }

    return out;
}

export function feePlanPayload(data: FeePlanFormData) {
    // 一律自訂期別；雙科／三科價寫在各期各科，不再產生月繳組合
    const bundles: FeePlanBundleRow[] = [];

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
        unit: 'custom_period' as const,
        session_block_size: null,
        list_price: Number(data.list_price || 0),
        quarter_price: null,
        periods: data.periods.map((period) => ({
            start_month:
                period.start_month === '' ? '' : Number(period.start_month),
            end_month: period.end_month === '' ? '' : Number(period.end_month),
            course_prices: Object.fromEntries(
                data.course_ids.map((id) => {
                    const row =
                        period.course_prices[id] ??
                        period.course_prices[Number(id)] ??
                        emptyPeriodCoursePrice();

                    return [
                        id,
                        {
                            total_price:
                                row.total_price === ''
                                    ? ''
                                    : Number(row.total_price),
                            double_price: emptyToNull(row.double_price),
                            triple_price: emptyToNull(row.triple_price),
                        },
                    ];
                }),
            ),
        })),
        quarter_single_price: null,
        quarter_double_price: null,
        material_fee: Number(data.material_fee || 0),
        material_unit: data.material_unit,
        sort_order: Number(data.sort_order || 0),
        is_active: data.is_active,
        bundles: bundles.map((bundle) => ({
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
