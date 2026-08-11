// Tanggal & waktu — aplikasi selalu memakai zona Asia/Jakarta (WIB),
// terlepas dari zona waktu browser. Kolom `date` di DB adalah "tanggal
// bisnis" WIB tanpa timezone, jadi jangan pernah pakai toISOString()
// (UTC) untuk menghasilkan tanggal — bisa geser -1 hari antara 00:00-06:59 WIB.

const APP_TIMEZONE = 'Asia/Jakarta';

/** Tanggal lokal WIB hari ini dalam format Y-m-d. */
export function todayWIB(): string {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: APP_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());

    const value = (type: Intl.DateTimeFormatPartTypes): string =>
        parts.find((part) => part.type === type)?.value ?? '';

    return `${value('year')}-${value('month')}-${value('day')}`;
}

/**
 * Format tanggal untuk tampilan (id-ID) dalam zona WIB.
 * String `Y-m-d` diperlakukan sebagai tanggal bisnis WIB (tanpa timezone);
 * string/Date ber-timestamp dikonversi dari zona asalnya ke WIB.
 */
export function formatDateID(
    value: string | number | Date,
    options: Intl.DateTimeFormatOptions = {},
): string {
    const date =
        typeof value === 'string' && !value.includes('T')
            ? new Date(`${value}T00:00:00`)
            : new Date(value);

    return date.toLocaleDateString('id-ID', {
        timeZone: APP_TIMEZONE,
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        ...options,
    });
}

/**
 * Format tanggal + jam untuk tampilan (id-ID) dalam zona WIB.
 */
export function formatDateTimeID(
    value: string | number | Date,
    options: Intl.DateTimeFormatOptions = {},
): string {
    return new Date(value).toLocaleString('id-ID', {
        timeZone: APP_TIMEZONE,
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        ...options,
    });
}
