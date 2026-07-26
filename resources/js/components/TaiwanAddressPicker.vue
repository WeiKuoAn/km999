<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Area = { ZipCode: string; AreaName: string };
type City = { CityName: string; AreaList: Area[] };

const city = defineModel<string>('city', { default: '' });
const district = defineModel<string>('district', { default: '' });
const zip = defineModel<string>('zip', { default: '' });
const detail = defineModel<string>('detail', { default: '' });

const cities = ref<City[]>([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const res = await fetch('/json/city.json');
        cities.value = (await res.json()) as City[];
    } finally {
        loading.value = false;
    }
});

const districts = computed(() => {
    const match = cities.value.find((c) => c.CityName === city.value);
    return match?.AreaList ?? [];
});

watch(city, () => {
    district.value = '';
    zip.value = '';
});

watch(district, (value) => {
    if (!value) {
        zip.value = '';
        return;
    }
    const match = districts.value.find((a) => a.AreaName === value);
    zip.value = match?.ZipCode ?? '';
});
</script>

<template>
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(7rem,9rem)_minmax(7rem,9rem)_4.5rem_minmax(0,1fr)]">
        <div class="grid gap-1">
            <Label class="text-xs text-muted-foreground">縣市</Label>
            <select
                v-model="city"
                class="h-9 w-full rounded-md border bg-background px-2 text-sm"
                :disabled="loading"
            >
                <option value="">請選擇</option>
                <option v-for="c in cities" :key="c.CityName" :value="c.CityName">
                    {{ c.CityName }}
                </option>
            </select>
        </div>
        <div class="grid gap-1">
            <Label class="text-xs text-muted-foreground">鄉鎮市區</Label>
            <select
                v-model="district"
                class="h-9 w-full rounded-md border bg-background px-2 text-sm"
                :disabled="loading || !city"
            >
                <option value="">請選擇</option>
                <option v-for="a in districts" :key="`${a.ZipCode}-${a.AreaName}`" :value="a.AreaName">
                    {{ a.AreaName }}
                </option>
            </select>
        </div>
        <div class="grid gap-1">
            <Label class="text-xs text-muted-foreground">郵遞區號</Label>
            <Input v-model="zip" class="h-9 px-2 text-sm" readonly placeholder="—" />
        </div>
        <div class="grid gap-1">
            <Label class="text-xs text-muted-foreground">詳細地址</Label>
            <Input v-model="detail" class="h-9 text-sm" placeholder="路街巷弄號樓" />
        </div>
    </div>
</template>
