/**
 * 課表彙整字串（以全形分號衔接多班）拆成多行顯示。
 */
export function scheduleLabelLines(label: string): string[] {
    if (label === '—' || label.trim() === '') {
        return [label || '—'];
    }
    const parts = label
        .split('；')
        .map((s) => s.trim())
        .filter((s) => s.length > 0);

    return parts.length > 0 ? parts : [label];
}
