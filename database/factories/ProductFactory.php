<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition() : array
    {
        return [
            'external_id' => Str::uuid(),
            'category_id' => Category::factory(),
            'name'        => $this->faker->sentence(3),
            'price'       => $this->faker->randomFloat(nbMaxDecimals: 2, min: 10, max: 2000),
            'currency'    => 'EUR',
            'image_url'   => $this->faker->imageUrl(),
            'brand'       => $this->faker->randomElement(['Samsung', 'LG', 'Sony', 'Philips', 'Hisense']),
            'product_url' => $this->faker->url(),
        ];
    }
}
