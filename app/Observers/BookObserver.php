<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Models\Book;
use App\Support\ActivityLogger;

final class BookObserver
{
    public function created(Book $book): void
    {
        ActivityLogger::log(ActivityAction::BookCreate, "Buku '{$book->judul}' dibuat", $book);
    }

    public function updated(Book $book): void
    {
        ActivityLogger::log(ActivityAction::BookUpdate, "Buku '{$book->judul}' diperbarui", $book);
    }

    public function deleted(Book $book): void
    {
        ActivityLogger::log(ActivityAction::BookDelete, "Buku '{$book->judul}' dihapus", $book);
    }

    public function restored(Book $book): void
    {
        ActivityLogger::log(ActivityAction::BookRestore, "Buku '{$book->judul}' dipulihkan", $book);
    }
}
