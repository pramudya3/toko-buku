/**
 * Normalisasi nomor HP Indonesia ke format internasional untuk wa.me.
 * Contoh: "0812-3102-9312" → "6281231029312"; "81231029312" → "6281231029312".
 */
export function normalizeWaNumber(
    phone: string | null | undefined,
): string | null {
    if (!phone) {
        return null;
    }

    const digits = phone.replace(/\D/g, '');

    if (digits.startsWith('0')) {
        return `62${digits.slice(1)}`;
    }

    if (digits.startsWith('8')) {
        return `62${digits}`;
    }

    if (digits.startsWith('62')) {
        return digits;
    }

    return null;
}

/**
 * URL chat WhatsApp (wa.me) dengan pesan awal.
 */
export function waMeUrl(
    phone: string | null | undefined,
    message?: string,
): string | null {
    const number = normalizeWaNumber(phone);

    if (number === null) {
        return null;
    }

    const query = message ? `?text=${encodeURIComponent(message)}` : '';

    return `https://wa.me/${number}${query}`;
}
