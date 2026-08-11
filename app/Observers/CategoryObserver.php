<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Models\Category;
use App\Support\ActivityLogger;

final class CategoryObserver
{
    public function created(Category $category): void
    {
        ActivityLogger::log(ActivityAction::CategoryCreate, "Kategori '{$category->nama}' dibuat", $category);
    }

    public function updated(Category $category): void
    {
        ActivityLogger::log(ActivityAction::CategoryUpdate, "Kategori '{$category->nama}' diperbarui", $category);
    }

    public function deleted(Category $category): void
    {
        ActivityLogger::log(ActivityAction::CategoryDelete, "Kategori '{$category->nama}' dihapus", $category);
    }
}
