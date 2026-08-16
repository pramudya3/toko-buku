<?php

namespace App\Models;

use App\Observers\ArticleCategoryObserver;
use Database\Factories\ArticleCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kategori konten artikel — terpisah dari categories (katalog buku).
 *
 * @property string $id
 * @property string $nama
 * @property string $slug
 */
#[Fillable(['nama', 'slug'])]
#[ObservedBy([ArticleCategoryObserver::class])]
class ArticleCategory extends Model
{
    /** @use HasFactory<ArticleCategoryFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
