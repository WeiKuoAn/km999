export type ParentPhone = { title: string; phone: string };

export type Sibling = { relation: string; name: string; school: string; grade: string };

export const SIBLING_RELATIONS = ['哥哥', '姊姊', '弟弟', '妹妹'] as const;

export const emptySibling = (): Sibling => ({ relation: '', name: '', school: '', grade: '' });

export const normalizeSiblings = (siblings: Sibling[]): Sibling[] | null => {
    const cleaned = siblings
        .map((s) => ({
            relation: s.relation.trim(),
            name: s.name.trim(),
            school: s.school.trim(),
            grade: s.grade.trim(),
        }))
        .filter((s) => s.name !== '' || s.school !== '' || s.grade !== '');

    return cleaned.length > 0 ? cleaned : null;
};

export type StudentAddress = {
    address_city: string;
    address_district: string;
    address_zip: string;
    address_detail: string;
};

export const defaultParentPhones = (): ParentPhone[] => [
    { title: '', phone: '' },
    { title: '', phone: '' },
];

export const normalizeParentPhones = (phones: ParentPhone[]): ParentPhone[] | null => {
    const cleaned = phones
        .map((p) => ({ title: p.title.trim(), phone: p.phone.trim() }))
        .filter((p) => p.title !== '' || p.phone !== '');

    return cleaned.length > 0 ? cleaned : null;
};

export const composeAddress = (address: StudentAddress): string | null => {
    const parts = [
        address.address_zip.trim(),
        address.address_city.trim() + address.address_district.trim(),
        address.address_detail.trim(),
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(' ') : null;
};

export const emptyToNull = (v: string): string | null => (v.trim() === '' ? null : v.trim());
