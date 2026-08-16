<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Models\ArticleCategory;
use App\Support\ActivityLogger;

final class ArticleCategoryObserver
{
    public function created(ArticleCategory $category): void
    {
        ActivityLogger::log(ActivityAction::ArticleCategoryCreate, "Kategori konten '{$category->nama}' dibuat", $category);
    }

    public function updated(ArticleCategory $category): void
    {
        ActivityLogger::log(ActivityAction::ArticleCategoryUpdate, "Kategori konten '{$category->nama}' diperbarui", $category);
    }

    public function deleted(ArticleCategory $category): void
    {
        ActivityLogger::log(ActivityAction::ArticleCategoryDelete, "Kategori konten '{$category->nama}' dihapus", $category);
    }
}
