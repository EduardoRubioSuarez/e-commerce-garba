<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StoreDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('El catálogo de demostración solo puede sembrarse en local o testing.');
        }
        DB::transaction(function (): void {
            Coupon::firstOrCreate(['code' => 'PROMO10'], ['kind' => 'percentage', 'value' => 1000,
                'currency' => StoreSetting::current()->currency, 'minimum_base_minor' => 0, 'max_uses' => 50,
                'applies_to_installers' => false, 'applies_to_manual_prices' => false, 'scope' => 'all', 'is_active' => true]);
            $category = Category::firstOrCreate(['slug' => 'demo-climatizacion'], ['name' => 'DEMOSTRACIÓN: Climatización']);
            foreach (['demo-minisplit-12000' => ['DEMOSTRACIÓN: Minisplit 12000 BTU', 100000], 'demo-minisplit-18000' => ['DEMOSTRACIÓN: Minisplit 18000 BTU', 150000]] as $slug => [$name, $cost]) {
                Product::firstOrCreate(['slug' => $slug], [
                    'category_id' => $category->id,
                    'name' => $name,
                    'description' => 'Producto ficticio para probar el catálogo. No representa una oferta comercial.',
                    'specifications' => 'Especificaciones ficticias de demostración.',
                    'equipment_contents' => 'Contenido ilustrativo: unidad interior y exterior.',
                    'base_cost_cents' => $cost,
                    'is_published' => false,
                    'is_active' => true,
                ]);
            }
        });
    }
}
