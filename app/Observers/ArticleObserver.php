<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Models\Article;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Cache;

final class ArticleObserver
{
    public function saving(Article $article): void
    {
        if ($article->isDirty('is_featured') && $article->is_featured) {
            Article::query()
                ->where('is_featured', true)
                ->when($article->exists, fn ($query) => $query->whereKeyNot($article->getKey()))
                ->update(['is_featured' => false]);
        }
    }

    public function created(Article $article): void
    {
        Cache::forget('storefront.article_facets');
        Cache::forget('storefront.article_categories');
        ActivityLogger::log(ActivityAction::ArticleCreate, "Artikel '{$article->judul}' dibuat", $article);
    }

    public function updated(Article $article): void
    {
        Cache::forget('storefront.article_facets');
        Cache::forget('storefront.article_categories');
        ActivityLogger::log(ActivityAction::ArticleUpdate, "Artikel '{$article->judul}' diperbarui", $article);
    }

    public function deleted(Article $article): void
    {
        Cache::forget('storefront.article_facets');
        Cache::forget('storefront.article_categories');
        ActivityLogger::log(ActivityAction::ArticleDelete, "Artikel '{$article->judul}' dihapus", $article);
    }

    public function restored(Article $article): void
    {
        if ($article->is_featured) {
            Article::query()
                ->where('is_featured', true)
                ->whereKeyNot($article->getKey())
                ->update(['is_featured' => false]);
        }

        Cache::forget('storefront.article_facets');
        Cache::forget('storefront.article_categories');
        ActivityLogger::log(ActivityAction::ArticleUpdate, "Artikel '{$article->judul}' dipulihkan", $article);
    }
}
