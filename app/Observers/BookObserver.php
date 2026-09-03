<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Events\Pos\PosMasterUpdated;
use App\Models\Book;
use App\Support\ActivityLogger;

final class BookObserver
{
    public function created(Book $book): void
    {
        ActivityLogger::log(ActivityAction::BookCreate, "Buku '{$book->judul}' dibuat", $book);
        broadcast(new PosMasterUpdated('books', now()->toIso8601String(), $book->id));
    }

    public function updated(Book $book): void
    {
        ActivityLogger::log(ActivityAction::BookUpdate, "Buku '{$book->judul}' diperbarui", $book);
        broadcast(new PosMasterUpdated('books', now()->toIso8601String(), $book->id));
    }

    public function deleted(Book $book): void
    {
        ActivityLogger::log(ActivityAction::BookDelete, "Buku '{$book->judul}' dihapus", $book);
        broadcast(new PosMasterUpdated('books', now()->toIso8601String(), $book->id));
    }

    public function restored(Book $book): void
    {
        ActivityLogger::log(ActivityAction::BookRestore, "Buku '{$book->judul}' dipulihkan", $book);
        broadcast(new PosMasterUpdated('books', now()->toIso8601String(), $book->id));
    }
}
