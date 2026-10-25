<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory()->seller(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'description' => fake()->sentence(12),
            'logo' => null,
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->streetAddress(),
            'city_id' => 278,
            'city_name' => 'Kota Medan',
            'province_name' => 'Sumatera Utara',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function inCity(int $cityId, string $cityName, string $provinceName): static
    {
        return $this->state(fn (array $attributes) => [
            'city_id' => $cityId,
            'city_name' => $cityName,
            'province_name' => $provinceName,
        ]);
    }
}
