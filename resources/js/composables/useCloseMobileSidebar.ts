import { useSidebar } from '@/components/ui/sidebar';

/** 平板／手機點選單連結後關閉側欄抽屜 */
export function useCloseMobileSidebar() {
    const { isMobile, setOpenMobile } = useSidebar();

    return () => {
        if (isMobile.value) {
            setOpenMobile(false);
        }
    };
}
