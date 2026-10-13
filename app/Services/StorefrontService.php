<?php

namespace App\Services;

use App\Enums\PriceTier;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use OverflowException;

class StorefrontService
{
    public function __construct(private PricingService $pricing) {}

    public function currentTier(Request $request): PriceTier
    {
        $id = $request->user()?->getAuthIdentifier();
        $user = $id === null ? null : User::find($id);

        return $user?->isActive() && $user->role === UserRole::Installer && $request->session()->get('auth.version') === $user->auth_version
            ? PriceTier::Installer : PriceTier::Public;
    }

    /**
     * @return array{id:int,name:string,slug:string,description:string,specifications:string,equipment_contents:string,capacity_btu:int|null,category:array{name:string,slug:string},photos:list<array{url:string,alt:string}>,price:array{total_cents:int,currency:string}|null}
     */
    public function productData(Product $product, PriceTier $tier, StoreSetting $setting): array
    {
        try {
            $line = $this->pricing->quoteLine($product, 1, $tier, $setting);
            $price = ['total_cents' => $line->totalCents, 'currency' => trim($setting->currency)];
        } catch (InvalidArgumentException|OverflowException) {
            $price = null;
        }

        return [
            'id' => $product->id,
            'name' => (string) $product->name,
            'slug' => (string) $product->slug,
            'description' => (string) $product->description,
            'specifications' => (string) $product->specifications,
            'equipment_contents' => (string) $product->equipment_contents,
            'capacity_btu' => $product->capacity_btu,
            'category' => ['name' => (string) $product->category->name, 'slug' => (string) $product->category->slug],
            'photos' => $product->photos->map(fn ($photo): array => ['url' => Storage::disk('public')->url($photo->path), 'alt' => (string) $photo->alt])->all(),
            'price' => $price,
        ];
    }
}
