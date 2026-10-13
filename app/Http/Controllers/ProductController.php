<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\StorefrontService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function show(Request $request, string $slug, StorefrontService $storefront): Response|JsonResponse
    {
        $model = Product::query()->where('slug', $slug)->where('is_active', true)->where('is_published', true)
            ->with(['category', 'photos', 'priceOverrides'])->firstOrFail();
        $tier = $storefront->currentTier($request);
        $product = $storefront->productData($model, $tier, StoreSetting::current());
        if ($request->expectsJson()) {
            return response()->json(['data' => $product])->header('Cache-Control', 'private, no-store');
        }

        return response()->view('store.product', compact('product', 'tier'))->header('Cache-Control', 'private, no-store');
    }
}
