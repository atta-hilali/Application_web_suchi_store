<?php

namespace App\Services;

use App\Models\RestaurantSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class HeroMedia
{
    public function current(): array
    {
        $values = RestaurantSetting::query()->whereIn('key', ['hero_media_type', 'hero_image_path', 'hero_video_path', 'hero_image_url', 'hero_video_url'])->pluck('value', 'key');
        $image = $values->get('hero_image_url') ?: config('hero.default_image');
        $video = $values->get('hero_video_url') ?: config('hero.default_video');
        if ($values->get('hero_image_path')) {
            $image = Storage::disk('public')->url($values['hero_image_path']);
        }
        if ($values->get('hero_video_path')) {
            $video = Storage::disk('public')->url($values['hero_video_path']);
        }

        return ['type' => $values->get('hero_media_type', config('hero.default_type')), 'image_url' => $image, 'video_url' => $video];
    }

    public function update(string $type, string $source, ?UploadedFile $file, ?string $url): array
    {
        $newPath = null;
        $oldPath = null;
        try {
            if ($source === 'upload') {
                $newPath = $file->store('hero', 'public');
                if (! $newPath) {
                    throw new \RuntimeException('Impossible de sauvegarder le fichier.');
                }
            }
            DB::transaction(function () use ($type, $source, $newPath, $url, &$oldPath) {
                // Serialize updates to this media slot and keep the old file until commit.
                RestaurantSetting::query()->firstOrCreate(['key' => 'hero_media_type'], ['value' => config('hero.default_type')]);
                RestaurantSetting::query()->where('key', 'hero_media_type')->lockForUpdate()->firstOrFail();
                if ($source !== 'existing') {
                    $oldPath = RestaurantSetting::value('hero_'.$type.'_path');
                    RestaurantSetting::query()->updateOrCreate(['key' => 'hero_'.$type.'_path'], ['value' => $newPath ?? '']);
                    RestaurantSetting::query()->updateOrCreate(['key' => 'hero_'.$type.'_url'], ['value' => $source === 'url' ? $url : '']);
                }
                $current = $this->current();
                if (! $current[$type.'_url']) {
                    throw ValidationException::withMessages(['file' => 'Choisissez un fichier ou un lien avant de publier.']);
                }
                RestaurantSetting::query()->where('key', 'hero_media_type')->update(['value' => $type]);
            }, 3);
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            throw $exception;
        }
        if ($oldPath && $oldPath !== $newPath && str_starts_with($oldPath, 'hero/')) {
            Storage::disk('public')->delete($oldPath);
        }

        return $this->current();
    }
}
