<?php

use App\Services\ImageService;
use Illuminate\Http\UploadedFile;

it('skips normalization for gif files to preserve animation', function (): void {
    // GIF minimal 1x1 — cukup untuk menguji bahwa file tidak dikonversi.
    $gif = UploadedFile::fake()->create('animasi.gif', 10, 'image/gif');

    $result = app(ImageService::class)->normalize($gif);

    expect($result->getClientOriginalExtension())->toBe('gif')
        ->and($result->getMimeType())->toBe('image/gif');
});

it('normalizes jpg files to jpeg', function (): void {
    $jpg = UploadedFile::fake()->image('foto.jpg');

    $result = app(ImageService::class)->normalize($jpg);

    expect($result->getMimeType())->toBe('image/jpeg');
});
