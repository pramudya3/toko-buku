<?php

use App\Models\Book;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Rollback migration UUID harus mengembalikan primary key integer,
 * lalu migrate ulang menghasilkan uuid lagi (plan §3.3 — up → down → up).
 */
it('rolls back the uuid migration to integer keys and migrates again', function (): void {
    // Hitung langkah dinamis: dari migration convert-uuid (000639) sampai
    // terbaru — rollback sampai tepat sebelum UUID supaya id kembali integer.
    // (tidak di-hardcode supaya tidak pecah saat migrasi baru ditambahkan).
    $step = collect(File::files(database_path('migrations')))
        ->filter(fn (SplFileInfo $file): bool => $file->getFilename() >= '2026_08_10_000639_convert_primary_keys_to_uuid.php')
        ->count();

    Artisan::call('migrate:rollback', ['--step' => $step]);

    expect(Schema::getColumnType('books', 'id'))->toBe('integer')
        ->and(Schema::getColumnType('orders', 'id'))->toBe('integer')
        ->and(Schema::getColumnType('inventory_movements', 'id'))->toBe('integer')
        ->and(Schema::getColumnType('villages', 'id'))->toBe('integer')
        ->and(Schema::hasTable('uuid_migration_map'))->toBeFalse();

    // Migrate ulang — id kembali uuid (varchar di sqlite).
    Artisan::call('migrate');

    expect(Schema::getColumnType('books', 'id'))->toBe('varchar')
        ->and(Schema::getColumnType('orders', 'id'))->toBe('varchar')
        ->and(Schema::getColumnType('villages', 'id'))->toBe('varchar')
        ->and(Schema::hasTable('uuid_migration_map'))->toBeTrue();

    // Relasi tetap bisa dibuat setelah up kedua.
    $book = Book::factory()->create();

    expect(Str::isUuid($book->id))->toBeTrue();
});
