export const PRICING_GROUPS = [
    { value: 'core', label: '核心科（英／數／理）' },
    { value: 'humanities', label: '國社生' },
    { value: 'research', label: '理化科研' },
    { value: 'other', label: '其他' },
] as const;

export type PricingGroupValue = (typeof PRICING_GROUPS)[number]['value'];

export function pricingGroupLabel(value: string | null | undefined): string {
    if (!value) return '未設定';
    return PRICING_GROUPS.find((g) => g.value === value)?.label ?? value;
}
