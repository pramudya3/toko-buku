<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Observers\ArticleObserver;
use App\Services\ArticleSanitizer;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Artikel untuk menu storefront (daftar & baca) dan CMS panel admin.
 *
 * @property string $id
 * @property string $judul
 * @property string $slug
 * @property string|null $article_category_id
 * @property string|null $penulis
 * @property string $ringkasan
 * @property string $isi
 * @property string|null $motif
 * @property string|null $cover_url
 * @property bool $is_active
 * @property string|null $published_at
 */
#[Fillable([
    'judul', 'slug', 'article_category_id', 'penulis', 'ringkasan', 'isi',
    'motif', 'cover_url', 'is_active', 'published_at',
])]
#[ObservedBy([ArticleObserver::class])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Motif SVG fallback (tanpa cover) — cocok dengan ArticleThumb storefront.
     *
     * @return array<string, string>
     */
    public static function motifOptions(): array
    {
        return [
            'stack' => 'Tumpukan buku',
            'manuscript' => 'Naskah',
            'readers' => 'Pembaca',
            'quote' => 'Kutipan',
            'shelf' => 'Rak buku',
            'pencil' => 'Pensil',
            'lamp' => 'Lampu',
        ];
    }

    /**
     * @return BelongsTo<ArticleCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    /**
     * Artikel yang tampil di storefront: aktif dan sudah terbit.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhereDate('published_at', '<=', today()));
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'published_at' => DateOnly::class,
        ];
    }

    /**
     * Isi artikel disimpan sebagai HTML — teks polos dikonversi & disaring
     * otomatis lewat ArticleSanitizer di setiap penyimpanan (controller,
     * factory, seeder).
     *
     * @return Attribute<string, string>
     */
    protected function isi(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => ['isi' => ArticleSanitizer::normalize($value)],
        );
    }
}
