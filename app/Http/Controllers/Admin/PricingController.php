<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PriceTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePricingRequest;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\PricingAdministrationService;
use App\Services\PricingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use OverflowException;

class PricingController extends Controller
{
    public function __construct(private PricingAdministrationService $administration) {}

    public function edit(PricingService $pricing): View
    {
        Gate::authorize('viewAny', Product::class);
        $settings = StoreSetting::current();
        $products = Product::query()->with('priceOverrides')->orderBy('id')->paginate(20);
        $previews = [];
        foreach ($products as $product) {
            foreach (PriceTier::cases() as $tier) {
                try {
                    $previews[$product->id][$tier->value] = $pricing->quoteLine($product, 1, $tier, $settings);
                } catch (InvalidArgumentException|OverflowException $exception) {
                    $previews[$product->id][$tier->value] = null;
                }
            }
        }

        return view('admin.pricing.edit', compact('settings', 'products', 'previews'));
    }

    public function update(UpdatePricingRequest $request): RedirectResponse
    {
        $product = $request->route('product');
        if ($product instanceof Product) {
            $this->administration->updateOverrides($product, $request->overrideChanges(), $request->user());
        } else {
            $this->administration->updateSettings($request->settingsChanges(), $request->user());
        }

        $page = $request->integer('page', 1);

        return redirect()->route('admin.pricing.edit', $page > 1 ? ['page' => $page] : [])->with('success', 'Precios actualizados.');
    }
}
