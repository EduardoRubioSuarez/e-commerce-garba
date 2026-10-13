<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductPhotoRequest;
use App\Http\Requests\UpdateProductPhotoRequest;
use App\Models\Product;
use App\Models\ProductPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ProductPhotoController extends Controller
{
    /**
     * Store a newly created photo for the specified product.
     *
     * @throws Throwable
     */
    public function store(StoreProductPhotoRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $validated = $request->validated();
        $file = $request->file('photo');

        if (! $file || ! $file->isValid()) {
            throw new InvalidArgumentException('El archivo de imagen no es válido.');
        }

        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new InvalidArgumentException('Tipo de archivo o imagen no permitido.'),
        };

        $filename = Str::uuid()->toString().'.'.$extension;
        $directory = 'product-photos/'.$product->id;

        $storedPath = $directory.'/'.$filename;
        $disk = Storage::disk('public');

        try {
            $path = $disk->putFileAs($directory, $file, $filename);

            if ($path === false) {
                throw new RuntimeException('No se pudo guardar la imagen en el almacenamiento público.');
            }

            DB::transaction(function () use ($product, $validated, $storedPath): void {
                $photo = $product->photos()->create([
                    'path' => $storedPath,
                    'alt' => $validated['alt'],
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                ]);
                if (! $photo->exists) {
                    throw new RuntimeException('No se pudo registrar la fotografía.');
                }
            });
        } catch (Throwable $e) {
            if (! $disk->delete($storedPath)) {
                report(new RuntimeException('No se pudo limpiar la fotografía nueva tras fallar su registro.', previous: $e));
            }

            throw $e;
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Foto agregada correctamente.');
    }

    /**
     * Update the specified photo details for the specified product.
     */
    public function update(UpdateProductPhotoRequest $request, Product $product, ProductPhoto $photo): RedirectResponse
    {
        Gate::authorize('update', $product);

        if ($photo->product_id !== $product->id) {
            abort(404);
        }

        $validated = $request->validated();

        $photo->update([
            'alt' => $validated['alt'],
            'sort_order' => (int) ($validated['sort_order'] ?? $photo->sort_order),
        ]);

        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Foto actualizada correctamente.');
    }
}
