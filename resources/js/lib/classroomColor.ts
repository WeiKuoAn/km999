/** 未設定班級顏色時的預設色（slate-500） */
export const DEFAULT_CLASSROOM_COLOR = '#64748b';

export function normalizeClassroomHex(input: string | null | undefined): string {
    if (input && /^#[0-9A-Fa-f]{6}$/.test(input)) {
        return input;
    }

    return DEFAULT_CLASSROOM_COLOR;
}

/** 將使用者輸入整理為 #RRGGBB；留空則回傳空字串 */
export function sanitizeClassroomHexInput(raw: string): string {
    const v = raw.trim();
    if (v === '') {
        return '';
    }
    const withHash = v.startsWith('#') ? v : `#${v}`;
    if (/^#[0-9A-Fa-f]{6}$/i.test(withHash)) {
        return withHash.toLowerCase();
    }

    return v;
}

/** 行事曆區塊：淡底 + 框線 + 左側色條 */
export function classroomCalendarSurface(hex: string | null | undefined): {
    backgroundColor: string;
    borderColor: string;
    borderLeftColor: string;
    borderLeftWidth: string;
} {
    const h = normalizeClassroomHex(hex);
    const r = Number.parseInt(h.slice(1, 3), 16);
    const g = Number.parseInt(h.slice(3, 5), 16);
    const b = Number.parseInt(h.slice(5, 7), 16);

    return {
        backgroundColor: `rgba(${r}, ${g}, ${b}, 0.14)`,
        borderColor: `rgba(${r}, ${g}, ${b}, 0.3)`,
        borderLeftColor: h,
        borderLeftWidth: '3px',
    };
}

export function classroomSwatchStyle(hex: string | null | undefined): { backgroundColor: string } {
    return { backgroundColor: normalizeClassroomHex(hex) };
}
