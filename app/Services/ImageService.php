<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class ImageService
{
    public function __construct(
        private readonly ImageManager $images = new ImageManager(new Driver),
    ) {}

    /**
     * Normalisasi gambar upload: resize maks 1600px, konversi ke JPEG 82%,
     * area transparan diisi putih. Mengembalikan UploadedFile baru — file
     * asli tidak diubah. Bila gagal diproses, file asli dikembalikan agar
     * validasi server (rule image) yang menolak.
     */
    public function normalize(UploadedFile $file, int $maxDimension = 1600, int $quality = 82): UploadedFile
    {
        // GIF (animasi): lewati konversi — encode ke JPEG menghilangkan animasi.
        if (strtolower($file->getClientOriginalExtension()) === 'gif') {
            return $file;
        }

        try {
            $image = $this->images->decodeSplFileInfo($file);
            $image->scaleDown(width: $maxDimension, height: $maxDimension);
            $image->fillTransparentAreas('#ffffff');

            $temp = tempnam(sys_get_temp_dir(), 'bookimg_');

            if ($temp === false) {
                return $file;
            }

            $image->encodeUsingMediaType('image/jpeg', quality: $quality)->save($temp);

            return new UploadedFile($temp, $file->getClientOriginalName(), 'image/jpeg', null, true);
        } catch (Throwable) {
            return $file;
        }
    }
}
