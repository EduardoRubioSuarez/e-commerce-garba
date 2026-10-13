<?php

namespace App\Http\Controllers\Admin;

use App\Data\CartLineData;
use App\Enums\PriceTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\PricingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;
use RuntimeException;

class ProductController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Product::class);

        return view('admin.products.index', ['products' => Product::query()->with('category')->orderByDesc('id')->paginate(20), 'currency' => StoreSetting::current()->currency]);
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return $this->form(new Product(['is_active' => true, 'is_published' => false]));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->persistProduct($request->attributesForPersistence());

        return redirect()->route('admin.products.index')->with('success', 'Producto creado.');
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        return $this->form($product);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->persistProduct($request->attributesForPersistence(), $product);

        return redirect()->route('admin.products.index')->with('success', 'Producto actualizado.');
    }

    /** @param array<string, mixed> $attributes */
    private function persistProduct(array $attributes, ?Product $product = null): void
    {
        DB::transaction(function () use ($attributes, $product): void {
            StoreSetting::current();
            StoreSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $candidate = $product === null ? new Product : Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $candidate->fill($attributes);
            if (! $candidate->save() || ! $candidate->exists) {
                throw new RuntimeException('Product persistence was cancelled.');
            }
            if ($candidate->is_published) {
                try {
                    foreach (PriceTier::cases() as $tier) {
                        app(PricingService::class)->quote([new CartLineData($candidate->id, 1)], $tier);
                    }
                } catch (InvalidArgumentException|OverflowException $exception) {
                    throw ValidationException::withMessages(['is_published' => 'No se puede publicar: revisa los precios efectivos y los impuestos de ambas tarifas.']);
                }
            }
        }, 3);
    }

    private function form(Product $product): View
    {
        if ($product->exists) {
            $product->load('photos');
        }

        return view('admin.products.form', ['product' => $product, 'categories' => Category::query()->orderBy('name')->get(), 'currency' => StoreSetting::current()->currency]);
    }
}
