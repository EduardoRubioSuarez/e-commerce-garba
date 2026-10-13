<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductPhoto>
 */
class ProductPhotoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ProductPhoto>
     */
    protected $model = ProductPhoto::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'path' => 'product-photos/'.Str::uuid().'.png',
            'alt' => fake()->sentence(3),
            'sort_order' => 0,
        ];
    }
}
