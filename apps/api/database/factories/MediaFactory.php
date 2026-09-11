<?php

namespace Database\Factories;

use App\Models\Fursona;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    public function definition(): array
    {
        $id = (string) Str::uuid();

        return [
            'fursona_id' => Fursona::factory(),
            'owner_id' => fn (array $attrs) => Fursona::find($attrs['fursona_id'])->owner_id,
            'kind' => 'art2d',
            'storage_key' => "media/x/y/{$id}.jpg",
            'display_key' => "media/x/y/{$id}_display.webp",
            'thumb_key' => "media/x/y/{$id}_thumb.webp",
            'mime' => 'image/jpeg',
            'width' => 1600,
            'height' => 1200,
            'bytes' => 300_000,
            'is_nsfw' => false,
            'caption' => fake()->words(2, true),
            'sort_order' => 0,
            'status' => 'active',
        ];
    }

    public function nsfw(): static
    {
        return $this->state(fn () => ['is_nsfw' => true]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => 'processing', 'display_key' => null, 'thumb_key' => null]);
    }

    public function override(string $visibility): static
    {
        return $this->state(fn () => ['visibility_override' => $visibility]);
    }
}
