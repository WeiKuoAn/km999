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

export type FeePlanFormData = {
    academic_year_id: string;
    grade_level_id: string;
    course_ids: number[];
    group_name: string;
    pricing_group: string;
    unit: 'month' | 'session_block';
    session_block_size: number | '';
    list_price: number;
    quarter_price: number | '';
    material_fee: number;
    material_unit: 'term' | 'subject' | 'class_day';
    sort_order: number;
    is_active: boolean;
    bundles: FeePlanBundleRow[];
};

const emptyToNull = (v: number | string | undefined) =>
    v === '' || v === undefined || Number.isNaN(Number(v)) ? null : Number(v);

export function feePlanPayload(data: FeePlanFormData) {
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
        unit: data.unit,
        session_block_size:
            data.unit === 'session_block'
                ? Number(data.session_block_size || 4)
                : null,
        list_price: Number(data.list_price || 0),
        quarter_price: emptyToNull(data.quarter_price),
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
