<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Models\Article;
use App\Support\ActivityLogger;

final class ArticleObserver
{
    public function created(Article $article): void
    {
        ActivityLogger::log(ActivityAction::ArticleCreate, "Artikel '{$article->judul}' dibuat", $article);
    }

    public function updated(Article $article): void
    {
        ActivityLogger::log(ActivityAction::ArticleUpdate, "Artikel '{$article->judul}' diperbarui", $article);
    }

    public function deleted(Article $article): void
    {
        ActivityLogger::log(ActivityAction::ArticleDelete, "Artikel '{$article->judul}' dihapus", $article);
    }
}
