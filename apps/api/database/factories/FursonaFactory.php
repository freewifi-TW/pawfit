<?php

namespace Database\Factories;

use App\Models\Fursona;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fursona>
 */
class FursonaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->firstName(),
            'species' => fake()->randomElement(['赤狐', '雪豹', '水獺', '龍', '狼']),
            'bio' => fake()->sentence(),
            'tags' => ['犬科', '標準體型'],
            'palette' => [
                ['hex' => '#D9642A', 'name' => '主毛色', 'note' => '', 'sort' => 0],
                ['hex' => '#F4E7D3', 'name' => '腹毛', 'note' => '', 'sort' => 1],
            ],
            'visibility' => 'public',
            'is_nsfw' => false,
            'is_representative' => false,
        ];
    }

    public function visibility(string $v): static
    {
        return $this->state(fn () => ['visibility' => $v]);
    }

    public function nsfw(): static
    {
        return $this->state(fn () => ['is_nsfw' => true]);
    }
}
