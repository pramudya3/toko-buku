<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import type { SelectOption } from '@/components/SearchableSelect.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    cities as adminCities,
    districts as adminDistricts,
    provinces as adminProvinces,
    villages as adminVillages,
} from '@/routes/admin/address';
import {
    cities as publicCities,
    districts as publicDistricts,
    provinces as publicProvinces,
    villages as publicVillages,
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
    kelurahan: string;
    village_code: string;
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
const villages = ref<Option[]>([]);
const loadingCities = ref(false);
const loadingDistricts = ref(false);
const loadingVillages = ref(false);

const provinceName = ref(props.modelValue.provinsi ?? '');
const cityName = ref(props.modelValue.kabupaten_kota ?? '');
const districtName = ref(props.modelValue.kecamatan ?? '');
const villageName = ref(props.modelValue.kelurahan ?? '');
const kodePos = ref(props.modelValue.kode_pos ?? '');
const alamat = ref(props.modelValue.alamat ?? '');

const provincesRequest = useHttp();
const citiesRequest = useHttp();
const districtsRequest = useHttp();
const villagesRequest = useHttp();

const hydrating = ref(false);

const currentValue = computed<AddressValue>(() => ({
    provinsi: provinceName.value,
    kabupaten_kota: cityName.value,
    kecamatan: districtName.value,
    kelurahan: villageName.value,
    village_code: villageCode.value,
    kode_pos: kodePos.value,
    alamat: alamat.value,
}));

const villageCode = ref(props.modelValue.village_code ?? '');

// Opsi untuk SearchableSelect (value = nama, dipakai juga sebagai nilai form).
const provinceOptions = computed<SelectOption[]>(() =>
    provinces.value.map((p) => ({ value: p.name, label: p.name })),
);

const cityOptions = computed<SelectOption[]>(() =>
    cities.value.map((c) => ({ value: c.name, label: c.name })),
);

const districtOptions = computed<SelectOption[]>(() =>
    districts.value.map((d) => ({ value: d.name, label: d.name })),
);

const villageOptions = computed<SelectOption[]>(() =>
    villages.value.map((v) => ({
        value: v.name,
        label: v.name,
        hint: v.kode_pos ? `POS ${v.kode_pos}` : null,
    })),
);

function syncToParent() {
    emit('update:modelValue', currentValue.value);
}

// Setiap perubahan field (termasuk ketikan manual alamat/kode pos) langsung
// disinkronkan ke parent — tanpa ini tombol submit di halaman induk tidak
// pernah melihat nilai alamat yang diketik.
watch(currentValue, () => syncToParent());

const routes = computed(() =>
    props.endpoint === 'public'
        ? {
              provinces: publicProvinces,
              cities: publicCities,
              districts: publicDistricts,
              villages: publicVillages,
          }
        : {
              provinces: adminProvinces,
              cities: adminCities,
              districts: adminDistricts,
              villages: adminVillages,
          },
);

async function loadProvinces() {
    await new Promise<void>((resolve) => {
        provincesRequest.get(routes.value.provinces().url, {
            onSuccess: (data) => {
                provinces.value = data as Option[];
                resolve();
            },
            onError: () => resolve(),
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
                onError: () => resolve(),
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
                onError: () => resolve(),
            },
        );
    });

    loadingDistricts.value = false;
}

async function loadVillages(district: string) {
    const option = districts.value.find((d) => d.name === district);

    if (!option) {
        villages.value = [];

        return;
    }

    loadingVillages.value = true;
    villages.value = [];
    villageName.value = '';
    villageCode.value = '';

    await new Promise<void>((resolve) => {
        villagesRequest.get(
            routes.value.villages({ query: { district_code: option.code } })
                .url,
            {
                onSuccess: (data) => {
                    villages.value = data as Option[];
                    resolve();
                },
                onError: () => resolve(),
            },
        );
    });

    loadingVillages.value = false;
}

function onProvinceChange(value: string) {
    provinceName.value = value;
    cityName.value = '';
    districtName.value = '';
    villageName.value = '';
    villageCode.value = '';
    kodePos.value = '';
    syncToParent();

    if (value) {
        void loadCities(value);
    }
}

function onCityChange(value: string) {
    cityName.value = value;
    districtName.value = '';
    villageName.value = '';
    villageCode.value = '';
    kodePos.value = '';
    syncToParent();

    if (value) {
        void loadDistricts(value);
    }
}

function onDistrictChange(value: string) {
    districtName.value = value;
    villageName.value = '';
    villageCode.value = '';

    const district = districts.value.find((d) => d.name === value);

    // Kode pos kecamatan sebagai dasar; nanti di-override kelurahan saat dipilih.
    // Kecamatan tanpa data → kosongkan (jangan simpan nilai stale).
    kodePos.value = district?.kode_pos ?? '';

    syncToParent();

    if (value) {
        void loadVillages(value);
    }
}

function onVillageChange(value: string) {
    villageName.value = value;

    const village = villages.value.find((v) => v.name === value);

    if (village) {
        villageCode.value = village.code;

        // Prioritas kode pos kelurahan/desa; fallback ke kode pos kecamatan.
        const district = districts.value.find(
            (d) => d.name === districtName.value,
        );
        kodePos.value = village.kode_pos ?? district?.kode_pos ?? '';
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

    if (preset.kecamatan) {
        await loadVillages(preset.kecamatan);
    }

    villageName.value = preset.kelurahan ?? '';
    villageCode.value = preset.village_code ?? '';

    // Kode pos kosong di preset → coba dari kelurahan, fallback ke kecamatan.
    if (!kodePos.value) {
        const village = preset.kelurahan
            ? villages.value.find((v) => v.name === preset.kelurahan)
            : undefined;
        const district = preset.kecamatan
            ? districts.value.find((d) => d.name === preset.kecamatan)
            : undefined;

        kodePos.value = village?.kode_pos ?? district?.kode_pos ?? '';
    }

    // Parent (mis. form checkout) membaca nilai via v-model — pastikan
    // tersinkron meski user tidak menyentuh field apa pun.
    syncToParent();

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
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="grid gap-2">
            <Label for="provinsi">Provinsi</Label>
            <SearchableSelect
                v-model="provinceName"
                :options="provinceOptions"
                name="provinsi"
                placeholder="Pilih provinsi"
                search-placeholder="Cari provinsi..."
                :disabled="!provinces.length || hydrating"
                @update:model-value="onProvinceChange"
            />
        </div>

        <div class="grid gap-2">
            <Label for="kabupaten_kota">Kota/Kabupaten</Label>
            <SearchableSelect
                v-model="cityName"
                :options="cityOptions"
                name="kabupaten_kota"
                :placeholder="
                    provinceName
                        ? 'Pilih kota/kabupaten'
                        : 'Pilih provinsi dulu'
                "
                search-placeholder="Cari kota/kabupaten..."
                :disabled="!cities.length || loadingCities || hydrating"
                @update:model-value="onCityChange"
            />
        </div>

        <div class="grid gap-2">
            <Label for="kecamatan">Kecamatan</Label>
            <SearchableSelect
                v-model="districtName"
                :options="districtOptions"
                name="kecamatan"
                :placeholder="
                    cityName ? 'Pilih kecamatan' : 'Pilih kota/kabupaten dulu'
                "
                search-placeholder="Cari kecamatan..."
                :disabled="!districts.length || loadingDistricts || hydrating"
                @update:model-value="onDistrictChange"
            />
        </div>

        <div class="grid gap-2">
            <Label for="kelurahan">Kelurahan/Desa</Label>
            <SearchableSelect
                v-model="villageName"
                :options="villageOptions"
                name="kelurahan"
                :placeholder="
                    districtName ? 'Pilih kelurahan' : 'Pilih kecamatan dulu'
                "
                search-placeholder="Cari kelurahan/desa..."
                :disabled="!villages.length || loadingVillages || hydrating"
                @update:model-value="onVillageChange"
            />
            <!-- Kode kelurahan/desa — dikirim bersama form agar tersimpan. -->
            <input type="hidden" name="village_code" :value="villageCode" />
        </div>

        <div class="grid gap-2">
            <Label for="kode_pos">Kode Pos</Label>
            <Input
                id="kode_pos"
                name="kode_pos"
                v-model="kodePos"
                :disabled="hydrating || !districtName"
                :placeholder="
                    districtName
                        ? 'Otomatis dari kelurahan'
                        : 'Pilih kecamatan dulu'
                "
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
