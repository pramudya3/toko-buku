<?php

namespace App\Models;

use Database\Factories\BookImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $book_id
 * @property string $image_url
 * @property int $urutan
 */
#[Fillable(['book_id', 'image_url', 'urutan'])]
class BookImage extends Model
{
    /** @use HasFactory<BookImageFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }
}
