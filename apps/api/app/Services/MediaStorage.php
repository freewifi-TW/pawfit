<?php

namespace App\Services;

use App\Models\Fursona;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** R2 / MinIO 物件的 key 規則、presign 與清理。 */
class MediaStorage
{
    public function keyPrefix(Fursona $fursona): string
    {
        return "media/{$fursona->owner_id}/{$fursona->id}/";
    }

    public function newOriginalKey(Fursona $fursona, string $contentType): string
    {
        $ext = match ($contentType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };

        return $this->keyPrefix($fursona).Str::uuid().'.'.$ext;
    }

    public function belongsTo(string $key, Fursona $fursona): bool
    {
        return str_starts_with($key, $this->keyPrefix($fursona)) && ! str_contains($key, '..');
    }

    /** @return array{url: string, headers: array<string, string>} */
    public function presignedPut(string $key, string $contentType): array
    {
        return Storage::disk('s3')->temporaryUploadUrl(
            $key,
            now()->addMinutes(config('pawfit.media.presign_ttl')),
            ['ContentType' => $contentType],
        );
    }

    public function signedGet(string $key): string
    {
        return Storage::disk('s3')->temporaryUrl($key, now()->addMinutes(config('pawfit.media.signed_url_ttl')));
    }

    public function exists(string $key): bool
    {
        return Storage::disk('s3')->exists($key);
    }

    public function size(string $key): int
    {
        return (int) Storage::disk('s3')->size($key);
    }

    public function deleteAll(Media $media): void
    {
        $keys = $media->allKeys();
        if ($keys) {
            Storage::disk('s3')->delete($keys);
        }
    }
}
