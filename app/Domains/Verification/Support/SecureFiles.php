<?php

namespace App\Domains\Verification\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Personal documents are encrypted with the application key before they
 * are written to the private disk, and decrypted only when staff open them.
 */
final class SecureFiles
{
    public static function put(UploadedFile $file, string $folder): string
    {
        $path = trim($folder, '/').'/'.Str::uuid().'.enc';
        Storage::disk('local')->put($path, Crypt::encryptString(base64_encode((string) file_get_contents($file->getRealPath()))));

        return $path;
    }

    public static function read(string $path): string
    {
        return (string) base64_decode(Crypt::decryptString((string) Storage::disk('local')->get($path)), true);
    }

    public static function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }
}
