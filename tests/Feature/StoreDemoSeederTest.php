<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\StoreDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class StoreDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_catalog_is_explicit_idempotent_and_creates_no_accounts(): void
    {
        $this->seed(StoreDemoSeeder::class);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('users', 0);
        $product = Product::query()->firstOrFail();
        $this->assertStringContainsString('DEMOSTRACIÓN', $product->name);
        $this->assertFalse($product->is_published);
        $product->update(['name' => 'Editado por administrador', 'base_cost_cents' => 777]);
        $this->seed(StoreDemoSeeder::class);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('products', 2);
        $this->assertSame('Editado por administrador', $product->fresh()->name);
        $this->assertSame(777, $product->fresh()->base_cost_cents);
    }

    public function test_production_is_refused_before_any_write(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        try {
            $this->app->make(StoreDemoSeeder::class)->run();
        } finally {
            $this->assertDatabaseCount('categories', 0);
            $this->assertDatabaseCount('products', 0);
            $this->assertDatabaseCount('product_photos', 0);
            $this->assertDatabaseCount('users', 0);
            $this->app['env'] = 'testing';
        }
    }
}
