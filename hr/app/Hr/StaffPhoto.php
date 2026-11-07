<?php

namespace App\Hr;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffPhoto
{
    public const SIZE = 480;

    public static function store(UploadedFile $file): string
    {
        $binary = self::squareJpeg($file->getPathname());
        $name = 'people/'.Str::uuid()->toString().'.jpg';
        Storage::disk('local')->put($name, $binary);

        return $name;
    }

    public static function ensureSized(User $member): void
    {
        if (! filled($member->photo_path) || ! Storage::disk('local')->exists($member->photo_path)) {
            return;
        }
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        $full = Storage::disk('local')->path($member->photo_path);
        $info = @getimagesize($full);
        $already = is_array($info)
            && ($info[0] ?? 0) === self::SIZE
            && ($info[1] ?? 0) === self::SIZE
            && str_ends_with(strtolower($member->photo_path), '.jpg');
        if ($already) {
            return;
        }

        try {
            $binary = self::squareJpeg($full);
        } catch (ValidationException) {
            return;
        }

        $name = 'people/'.Str::uuid()->toString().'.jpg';
        Storage::disk('local')->put($name, $binary);
        Storage::disk('local')->delete($member->photo_path);
        $member->photo_path = $name;
        $member->save();
    }

    public static function squareJpeg(string $path): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw ValidationException::withMessages([
                'photo' => 'This server cannot resize a photo yet. Switch on the GD image library, then choose the file again.',
            ]);
        }

        $info = @getimagesize($path);
        $type = $info[2] ?? 0;
        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if ($source === false) {
            throw ValidationException::withMessages([
                'photo' => 'Choose a JPEG or PNG photo from your computer.',
            ]);
        }

        $source = self::orient($source, $path, $type);
        $width = imagesx($source);
        $height = imagesy($source);
        $flat = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefill($flat, 0, 0, $white);
        imagecopy($flat, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        $side = min($width, $height);
        $cropX = intdiv($width - $side, 2);
        $cropY = intdiv($height - $side, 2);
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($square, $flat, 0, 0, $cropX, $cropY, self::SIZE, self::SIZE, $side, $side);
        imagedestroy($flat);

        ob_start();
        imagejpeg($square, null, 82);
        $binary = ob_get_clean();
        imagedestroy($square);

        return $binary === false ? '' : $binary;
    }

    private static function orient(\GdImage $image, string $path, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $turned = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
        if ($turned !== false && $turned !== $image) {
            imagedestroy($image);

            return $turned;
        }

        return $image;
    }
}
