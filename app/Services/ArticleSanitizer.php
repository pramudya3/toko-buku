<?php

namespace App\Services;

/**
 * Normalisasi & sanitasi isi artikel sebelum disimpan.
 *
 * - Teks polos (tanpa tag HTML) dikonversi ke HTML: paragraf dipisah baris
 *   kosong, baris berawalan ">" menjadi blockquote.
 * - Sanitasi allowlist: tag yang diizinkan editor WYSIWYG; script, on*,
 *   dan href berbahaya (javascript:) dibuang.
 */
final class ArticleSanitizer
{
    /** Tag HTML yang diizinkan pada isi artikel. */
    private const ALLOWED_TAGS = '<p><br><strong><em><u><s><mark><blockquote><h1><h2><h3><h4><h5><h6><ul><ol><li><a><code><pre><hr><div><span><label><input><img><sub><sup>';

    public static function normalize(string $value): string
    {
        $html = self::convertPlainText($value);

        return self::sanitize($html);
    }

    /**
     * Konversi teks polos → HTML bila input tidak mengandung tag HTML sama
     * sekali (kompatibilitas format lama, factory, dan seeder).
     */
    private static function convertPlainText(string $value): string
    {
        if (preg_match('/<\/?[a-zA-Z][^>]*>/', $value) === 1) {
            return $value;
        }

        $parts = preg_split('/\R\s*\R/', trim($value)) ?: [];

        $blocks = array_map(function (string $block): string {
            $block = trim($block);

            if ($block === '') {
                return '';
            }

            if (str_starts_with($block, '>')) {
                return '<blockquote>'.e(trim(substr($block, 1))).'</blockquote>';
            }

            return '<p>'.e($block).'</p>';
        }, $parts);

        return implode("\n", array_filter($blocks));
    }

    /**
     * Sanitasi HTML: buang tag di luar allowlist dan atribut berbahaya.
     */
    private static function sanitize(string $html): string
    {
        $html = strip_tags($html, self::ALLOWED_TAGS);

        // Buang event handler (onclick dkk.) dan href berbahaya (javascript:).
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/i', '', $html) ?? $html;
        $html = preg_replace('/href\s*=\s*(["\'])\s*javascript:[^"\']*\1/i', 'href="#"', $html) ?? $html;

        // Gambar hanya boleh dari URL http(s) — src lain (javascript:, data:, lokasi arbitrer) dibuang tag img-nya.
        $html = preg_replace_callback('/<img\b[^>]*>/i', function (array $matches): string {
            $tag = $matches[0];

            if (! preg_match('/\ssrc\s*=\s*(["\'])\s*(https?:\/\/[^"\']*)\1/i', $tag, $src)) {
                return '';
            }

            // Bangun ulang tag hanya dengan atribut aman: src, alt, width, height, title.
            $safe = '<img src="'.htmlspecialchars($src[2], ENT_QUOTES).'"';

            foreach (['alt', 'title'] as $attr) {
                if (preg_match('/\s'.$attr.'\s*=\s*(["\'])(.*?)\1/i', $tag, $m)) {
                    $safe .= ' '.$attr.'="'.htmlspecialchars($m[2], ENT_QUOTES).'"';
                }
            }

            foreach (['width', 'height'] as $attr) {
                if (preg_match('/\s'.$attr.'\s*=\s*(["\'])(\d+)\1/i', $tag, $m)) {
                    $safe .= ' '.$attr.'="'.$m[2].'"';
                }
            }

            return $safe.'>';
        }, $html) ?? $html;

        return trim($html);
    }
}
