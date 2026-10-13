<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\StorefrontService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CatalogController extends Controller
{
    public function index(Request $request, StorefrontService $storefront): Response|JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:180'],
            'capacity' => ['nullable', 'string', 'in:upto12,upto18,over18'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
        ]);
        $filters = ['q' => trim($validated['q'] ?? ''), 'category' => $validated['category'] ?? '', 'capacity' => $validated['capacity'] ?? ''];
        $bands = ['upto12' => ['Hasta 12,000', null, 12000], 'upto18' => ['De 12,001 a 18,000', 12001, 18000], 'over18' => ['Más de 18,000', 18001, null]];
        $inBand = fn (Builder $query, string $key): Builder => $query
            ->when($bands[$key][1] !== null, fn (Builder $q): Builder => $q->where('capacity_btu', '>=', $bands[$key][1]))
            ->when($bands[$key][2] !== null, fn (Builder $q): Builder => $q->where('capacity_btu', '<=', $bands[$key][2]));
        $visible = fn (Builder $query): Builder => $query->where('is_active', true)->where('is_published', true);
        $query = $visible(Product::query())->with(['category', 'photos', 'priceOverrides']);
        if ($filters['q'] !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn (Builder $search): Builder => $search
                ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("description LIKE ? ESCAPE '!'", [$pattern]));
        }
        if ($filters['category'] !== '') {
            $query->whereHas('category', fn (Builder $category): Builder => $category->where('slug', $filters['category']));
        }
        if ($filters['capacity'] !== '') {
            $inBand($query, $filters['capacity']);
        }
        $tier = $storefront->currentTier($request);
        $setting = StoreSetting::current();
        $products = $query->orderBy('id')->paginate(20)->appends($filters)
            ->through(fn (Product $product): array => $storefront->productData($product, $tier, $setting));
        if ($request->expectsJson()) {
            return response()->json([
                'data' => $products->items(),
                'meta' => ['current_page' => $products->currentPage(), 'last_page' => $products->lastPage(), 'per_page' => $products->perPage(), 'total' => $products->total()],
                'links' => ['next' => $products->nextPageUrl(), 'prev' => $products->previousPageUrl()],
            ])->header('Cache-Control', 'private, no-store');
        }
        $categories = Category::query()->whereHas('products', $visible)->withCount(['products as visible_count' => $visible])->orderBy('name')->get(['name', 'slug']);

        $capacities = collect($bands)->map(fn (array $band, string $key): array => ['key' => $key, 'label' => $band[0], 'count' => $inBand($visible(Product::query()), $key)->count()])->filter(fn (array $band): bool => $band['count'] > 0)->values();

        return response()->view('store.catalog', compact('products', 'categories', 'capacities', 'filters', 'tier'))
            ->header('Cache-Control', 'private, no-store');
    }
}
