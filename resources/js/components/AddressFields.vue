<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    cities as adminCities,
    districts as adminDistricts,
    provinces as adminProvinces,
} from '@/routes/admin/address';
import {
    cities as publicCities,
    districts as publicDistricts,
    provinces as publicProvinces,
} from '@/routes/wilayah';

type Option = {
    code: string;
    name: string;
    kode_pos?: string | null;
};

export type AddressValue = {
    provinsi: string;
    kabupaten_kota: string;
    kecamatan: string;
    kode_pos: string;
    alamat: string;
};

const props = withDefaults(
    defineProps<{
        modelValue: AddressValue;
        addressRequired?: boolean;
        endpoint?: 'admin' | 'public';
    }>(),
    {
        addressRequired: false,
        endpoint: 'admin',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: AddressValue): void;
}>();

const provinces = ref<Option[]>([]);
const cities = ref<Option[]>([]);
const districts = ref<Option[]>([]);
const loadingCities = ref(false);
const loadingDistricts = ref(false);

const provinceName = ref(props.modelValue.provinsi ?? '');
const cityName = ref(props.modelValue.kabupaten_kota ?? '');
const districtName = ref(props.modelValue.kecamatan ?? '');
const kodePos = ref(props.modelValue.kode_pos ?? '');
const alamat = ref(props.modelValue.alamat ?? '');

const provincesRequest = useHttp();
const citiesRequest = useHttp();
const districtsRequest = useHttp();

const hydrating = ref(false);

const currentValue = computed<AddressValue>(() => ({
    provinsi: provinceName.value,
    kabupaten_kota: cityName.value,
    kecamatan: districtName.value,
    kode_pos: kodePos.value,
    alamat: alamat.value,
}));

function syncToParent() {
    emit('update:modelValue', currentValue.value);
}

const routes = computed(() =>
    props.endpoint === 'public'
        ? { provinces: publicProvinces, cities: publicCities, districts: publicDistricts }
        : { provinces: adminProvinces, cities: adminCities, districts: adminDistricts },
);

async function loadProvinces() {
    await new Promise<void>((resolve) => {
        provincesRequest.get(routes.value.provinces().url, {
            onSuccess: (data) => {
                provinces.value = data as Option[];
                resolve();
            },
        });
    });
}

async function loadCities(province: string) {
    const option = provinces.value.find((p) => p.name === province);

    if (!option) {
        cities.value = [];
        districts.value = [];

        return;
    }

    loadingCities.value = true;
    cities.value = [];
    districts.value = [];
    cityName.value = '';
    districtName.value = '';

    await new Promise<void>((resolve) => {
        citiesRequest.get(
            routes.value.cities({ query: { province_code: option.code } }).url,
            {
                onSuccess: (data) => {
                    cities.value = data as Option[];
                    resolve();
                },
            },
        );
    });

    loadingCities.value = false;
}

async function loadDistricts(city: string) {
    const option = cities.value.find((c) => c.name === city);

    if (!option) {
        districts.value = [];

        return;
    }

    loadingDistricts.value = true;
    districts.value = [];
    districtName.value = '';

    await new Promise<void>((resolve) => {
        districtsRequest.get(
            routes.value.districts({ query: { city_code: option.code } }).url,
            {
                onSuccess: (data) => {
                    districts.value = data as Option[];
                    resolve();
                },
            },
        );
    });

    loadingDistricts.value = false;
}

async function onProvinceChange(value: string) {
    provinceName.value = value;
    cityName.value = '';
    districtName.value = '';
    kodePos.value = '';
    syncToParent();

    if (value) {
        await loadCities(value);
    }
}

async function onCityChange(value: string) {
    cityName.value = value;
    districtName.value = '';
    kodePos.value = '';
    syncToParent();

    if (value) {
        await loadDistricts(value);
    }
}

function onDistrictChange(value: string) {
    districtName.value = value;

    const district = districts.value.find((d) => d.name === value);

    if (district?.kode_pos) {
        kodePos.value = district.kode_pos;
    }

    syncToParent();
}

async function hydrateFromPreset() {
    const preset = props.modelValue;

    if (!preset.provinsi) {
        return;
    }

    hydrating.value = true;

    await loadProvinces();

    provinceName.value = preset.provinsi;
    await loadCities(preset.provinsi);

    if (preset.kabupaten_kota) {
        cityName.value = preset.kabupaten_kota;
        await loadDistricts(preset.kabupaten_kota);
    }

    districtName.value = preset.kecamatan ?? '';
    kodePos.value = preset.kode_pos ?? '';
    alamat.value = preset.alamat ?? '';

    hydrating.value = false;
}

onMounted(async () => {
    await loadProvinces();

    if (props.modelValue.provinsi) {
        await hydrateFromPreset();
    }
});
</script>

<template>
    <div class="grid gap-4 md:grid-cols-3">
        <div class="grid gap-2">
            <Label for="provinsi">Provinsi</Label>
            <Select
                :model-value="provinceName"
                :disabled="!provinces.length || hydrating"
                name="provinsi"
                @update:model-value="onProvinceChange"
            >
                <SelectTrigger id="provinsi">
                    <SelectValue placeholder="Pilih provinsi" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="province in provinces"
                        :key="province.code"
                        :value="province.name"
                    >
                        {{ province.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="grid gap-2">
            <Label for="kabupaten_kota">Kota/Kabupaten</Label>
            <Select
                :model-value="cityName"
                :disabled="!cities.length || loadingCities || hydrating"
                name="kabupaten_kota"
                @update:model-value="onCityChange"
            >
                <SelectTrigger id="kabupaten_kota">
                    <SelectValue
                        :placeholder="
                            provinceName
                                ? 'Pilih kota/kabupaten'
                                : 'Pilih provinsi dulu'
                        "
                    />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="city in cities"
                        :key="city.code"
                        :value="city.name"
                    >
                        {{ city.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="grid gap-2">
            <Label for="kecamatan">Kecamatan</Label>
            <Select
                :model-value="districtName"
                :disabled="!districts.length || loadingDistricts || hydrating"
                name="kecamatan"
                @update:model-value="onDistrictChange"
            >
                <SelectTrigger id="kecamatan">
                    <SelectValue
                        :placeholder="
                            cityName
                                ? 'Pilih kecamatan'
                                : 'Pilih kota/kabupaten dulu'
                        "
                    />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="district in districts"
                        :key="district.code"
                        :value="district.name"
                    >
                        {{ district.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="grid gap-2">
            <Label for="kode_pos">Kode Pos</Label>
            <Input
                id="kode_pos"
                name="kode_pos"
                v-model="kodePos"
                :disabled="hydrating || !districtName"
                :placeholder="districtName ? '65144' : 'Pilih kecamatan dulu'"
                maxlength="10"
            />
        </div>

        <div class="grid gap-2 md:col-span-2">
            <Label for="alamat">Alamat</Label>
            <Textarea
                id="alamat"
                name="alamat"
                v-model="alamat"
                :disabled="hydrating"
                :required="addressRequired"
                rows="2"
                placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan..."
            />
        </div>
    </div>
</template>
