<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Lembaga', href: '/admin/settings/lembaga' },
        ],
    },
});

import { Form, Head } from '@inertiajs/vue3';
import { Building2, Loader2 } from '@lucide/vue';
import { ref } from 'vue';
import SettingController from '@/actions/App/Http/Controllers/Admin/SettingController';
import AddressFields from '@/components/AddressFields.vue';
import type { AddressValue } from '@/components/AddressFields.vue';
import FormErrorAlert from '@/components/FormErrorAlert.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    nama_lembaga: string;
    logo_url: string;
    tagline: string;
    alamat_jalan: string;
    origin_postal_code: string;
    provinsi: string;
    kabupaten_kota: string;
    kecamatan: string;
    kelurahan: string;
    telepon: string;
    email: string;
    jam_operasional: string;
    hari_buka: string;
    jam_buka: string;
    jam_tutup: string;
    deskripsi: string;
    visi: string;
    misi: string;
    keamanan: string;
    syarat: string;
}>();

const address = ref<AddressValue>({
    provinsi: props.provinsi ?? '',
    kabupaten_kota: props.kabupaten_kota ?? '',
    kecamatan: props.kecamatan ?? '',
    kelurahan: props.kelurahan ?? '',
    village_code: '',
    kode_pos: props.origin_postal_code ?? '',
    alamat: props.alamat_jalan ?? '',
});
</script>

<template>
    <Head title="Pengaturan — Lembaga" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Pengaturan Toko
            </h1>
            <p class="text-sm text-muted-foreground">
                Identitas toko, logo, dan informasi kontak
            </p>
        </div>

        <Form
            v-bind="SettingController.updateLembaga.form()"
            class="flex flex-col gap-4"
            v-slot="{ errors, processing }"
        >
            <FormErrorAlert :errors="errors" />

            <Card>
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-medium"
                    >
                        <Building2 class="size-4" />
                        Logo Lembaga
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <img
                        v-if="logo_url"
                        :src="logo_url"
                        :alt="nama_lembaga || 'Logo'"
                        class="max-h-20 w-auto object-contain"
                    />
                    <div class="grid gap-2">
                        <Label for="logo">Ganti Logo</Label>
                        <Input
                            id="logo"
                            name="logo"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                        />
                        <p class="text-xs text-muted-foreground">
                            JPG/PNG/WebP, maksimal 2 MB — tampil di judul
                            sidebar, header toko, dan halaman Tentang Kami
                            (tanpa border).
                        </p>
                    </div>
                    <label
                        v-if="logo_url"
                        class="flex w-fit items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Checkbox name="hapus_logo" value="1" />
                        Hapus logo
                    </label>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-medium"
                    >
                        <Building2 class="size-4" />
                        Identitas Lembaga
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="nama_lembaga"
                                >Nama Toko / Lembaga *</Label
                            >
                            <Input
                                id="nama_lembaga"
                                name="nama_lembaga"
                                type="text"
                                required
                                :default-value="nama_lembaga"
                                placeholder="Pustaka Cahaya Peradaban"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="tagline">Tagline</Label>
                            <Input
                                id="tagline"
                                name="tagline"
                                type="text"
                                :default-value="tagline"
                                placeholder="Membaca untuk semua kalangan"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="deskripsi">Cerita Kami</Label>
                            <Textarea
                                id="deskripsi"
                                name="deskripsi"
                                rows="4"
                                :default-value="deskripsi"
                                placeholder="Cerita singkat / deskripsi toko — tampil di halaman Tentang Kami"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="visi">Visi</Label>
                            <Textarea
                                id="visi"
                                name="visi"
                                rows="2"
                                :default-value="visi"
                                placeholder="Tampil di halaman Tentang Kami"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="misi">Misi</Label>
                            <Textarea
                                id="misi"
                                name="misi"
                                rows="4"
                                :default-value="misi"
                                placeholder="Satu baris per misi — dirender sebagai daftar"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="keamanan">Keamanan & Kepercayaan</Label>
                            <Textarea
                                id="keamanan"
                                name="keamanan"
                                rows="3"
                                :default-value="keamanan"
                                placeholder="Transaksi aman, data terlindungi, dll."
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="syarat">Syarat & Prasyarat</Label>
                            <Textarea
                                id="syarat"
                                name="syarat"
                                rows="5"
                                :default-value="syarat"
                                placeholder="Ketentuan pembelian, pengiriman, retur, dll."
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="telepon">Telepon / WhatsApp</Label>
                                <Input
                                    id="telepon"
                                    name="telepon"
                                    type="tel"
                                    :default-value="telepon"
                                    placeholder="08xxxxxxxxxx"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="email">Email Kontak</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    :default-value="email"
                                    placeholder="halo@tokobuku.test"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="jam_operasional">Jam Operasional</Label>
                            <Input
                                id="jam_operasional"
                                name="jam_operasional"
                                type="text"
                                :default-value="jam_operasional"
                                placeholder="Senin–Sabtu, 08.00–17.00 WIB"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="hari_buka"
                                >Hari Buka (terstruktur)</Label
                            >
                            <Input
                                id="hari_buka"
                                name="hari_buka"
                                type="text"
                                :default-value="hari_buka"
                                placeholder="Senin–Sabtu"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-2">
                                <Label for="jam_buka">Jam Buka</Label>
                                <Input
                                    id="jam_buka"
                                    name="jam_buka"
                                    type="time"
                                    :default-value="jam_buka"
                                    placeholder="08:00"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="jam_tutup">Jam Tutup</Label>
                                <Input
                                    id="jam_tutup"
                                    name="jam_tutup"
                                    type="time"
                                    :default-value="jam_tutup"
                                    placeholder="17:00"
                                />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Alamat gudang — titik asal ongkir & alamat kop nota -->
            <Card>
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-medium"
                    >
                        <Building2 class="size-4" />
                        Alamat Lembaga
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <AddressFields v-model="address" :endpoint="'admin'" />
                    <input
                        type="hidden"
                        name="origin_postal_code"
                        :value="address.kode_pos"
                    />
                    <input
                        type="hidden"
                        name="alamat_jalan"
                        :value="address.alamat"
                    />
                    <input
                        type="hidden"
                        name="provinsi"
                        :value="address.provinsi"
                    />
                    <input
                        type="hidden"
                        name="kabupaten_kota"
                        :value="address.kabupaten_kota"
                    />
                    <input
                        type="hidden"
                        name="kecamatan"
                        :value="address.kecamatan"
                    />
                    <input
                        type="hidden"
                        name="kelurahan"
                        :value="address.kelurahan"
                    />
                    <p class="mt-3 text-xs text-muted-foreground">
                        Kode pos otomatis terisi dari kelurahan yang dipilih —
                        dipakai sebagai titik asal perhitungan ongkir Biteship
                        dan tampil di kop nota / invoice.
                    </p>
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Loader2 v-if="processing" class="size-4 animate-spin" />
                    {{ processing ? 'Menyimpan...' : 'Simpan' }}
                </Button>
            </div>
        </Form>
    </div>
</template>
