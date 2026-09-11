<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'google_id' => (string) fake()->unique()->numerify('1############'),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'pawfit_id' => Str::lower(fake()->unique()->lexify('user_?????')),
            'display_name' => fake()->firstName(),
            'nsfw_pref' => 'hide',
            'tos_accepted_at' => now(),
        ];
    }

    /** 尚未完成 onboarding。 */
    public function fresh(): static
    {
        return $this->state(fn () => ['pawfit_id' => null, 'tos_accepted_at' => null]);
    }

    public function adult(string $pref = 'show'): static
    {
        return $this->state(fn () => ['adult_confirmed_at' => now(), 'nsfw_pref' => $pref]);
    }

    public function banned(): static
    {
        return $this->state(fn () => ['is_banned' => true]);
    }
}
