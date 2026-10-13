<section aria-labelledby="photos-heading" class="mt-12 max-w-3xl">
    <h2 id="photos-heading" class="text-2xl font-semibold">Fotografías</h2>
    <p class="mt-1 text-sm leading-6 text-slate-600">La fotografía con el número de orden más bajo es la principal. El texto alternativo describe la imagen para quienes no pueden verla.</p>
    @php $isUploading = old('_photo_form', 'upload') === 'upload'; @endphp
    <form method="POST" action="{{ route('admin.product-photos.store', $product) }}" enctype="multipart/form-data" class="panel mt-6 grid gap-5 sm:grid-cols-2">
        @csrf
        <input type="hidden" name="_photo_form" value="upload">
        <h3 class="panel-title sm:col-span-2">Agregar una fotografía</h3>
        <div class="sm:col-span-2">
            <label for="new-photo" class="field-label">Archivo de imagen</label>
            <input id="new-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="photo-help @error('photo') photo-error @enderror" @error('photo') aria-invalid="true" @enderror class="input border-dashed bg-slate-50 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-800 file:px-3 file:py-1.5 file:font-semibold file:text-white">
            <p id="photo-help" class="field-help">JPEG, PNG o WebP. Máximo 5 MiB y 6000 × 6000 píxeles.</p>
            @error('photo') <p id="photo-error" role="alert" class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="new-alt" class="field-label">Texto alternativo</label>
            <input id="new-alt" name="alt" value="{{ $isUploading ? old('alt') : '' }}" required maxlength="255" @if($isUploading && $errors->has('alt')) aria-invalid="true" aria-describedby="new-alt-error" @endif class="input">
            <p class="field-help">Describe lo que se ve, por ejemplo "Minisplit blanco instalado en pared".</p>
            @if($isUploading && $errors->has('alt')) <p id="new-alt-error" role="alert" class="field-error">{{ $errors->first('alt') }}</p> @endif
        </div>
        <div>
            <label for="new-order" class="field-label">Orden</label>
            <input id="new-order" name="sort_order" type="number" min="0" max="1000000" step="1" value="{{ $isUploading ? old('sort_order', 0) : 0 }}" required @if($isUploading && $errors->has('sort_order')) aria-invalid="true" aria-describedby="new-order-error" @endif class="input">
            <p class="field-help">0 es la primera.</p>
            @if($isUploading && $errors->has('sort_order')) <p id="new-order-error" role="alert" class="field-error">{{ $errors->first('sort_order') }}</p> @endif
        </div>
        <div class="sm:col-span-2"><button type="submit" class="btn btn-primary">Subir fotografía</button></div>
    </form>
    <div class="mt-6 grid gap-6 sm:grid-cols-2">
        @forelse($product->photos as $photo)
            @php $isEditingPhoto = (string) old('_photo_form') === (string) $photo->id; @endphp
            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <img src="{{ Storage::disk('public')->url($photo->path) }}" alt="{{ $photo->alt }}" loading="lazy" class="aspect-[4/3] w-full bg-slate-50 object-contain">
                <form method="POST" action="{{ route('admin.product-photos.update', [$product, $photo]) }}" class="grid gap-4 border-t border-slate-200 p-5">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_photo_form" value="{{ $photo->id }}">
                    <div>
                        <label for="photo-{{ $photo->id }}-alt" class="field-label">Texto alternativo</label>
                        <input id="photo-{{ $photo->id }}-alt" name="alt" value="{{ $isEditingPhoto ? old('alt') : $photo->alt }}" required maxlength="255" @if($isEditingPhoto && $errors->has('alt')) aria-invalid="true" aria-describedby="photo-{{ $photo->id }}-alt-error" @endif class="input">
                        @if($isEditingPhoto && $errors->has('alt')) <p id="photo-{{ $photo->id }}-alt-error" role="alert" class="field-error">{{ $errors->first('alt') }}</p> @endif
                    </div>
                    <div>
                        <label for="photo-{{ $photo->id }}-order" class="field-label">Orden</label>
                        <input id="photo-{{ $photo->id }}-order" name="sort_order" type="number" min="0" max="1000000" step="1" value="{{ $isEditingPhoto ? old('sort_order') : $photo->sort_order }}" required @if($isEditingPhoto && $errors->has('sort_order')) aria-invalid="true" aria-describedby="photo-{{ $photo->id }}-order-error" @endif class="input">
                        @if($isEditingPhoto && $errors->has('sort_order')) <p id="photo-{{ $photo->id }}-order-error" role="alert" class="field-error">{{ $errors->first('sort_order') }}</p> @endif
                    </div>
                    <button type="submit" class="btn btn-secondary justify-self-start">Guardar fotografía</button>
                </form>
            </article>
        @empty
            <p class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600 sm:col-span-2">Este producto todavía no tiene fotografías. Sube la primera con el formulario de arriba.</p>
        @endforelse
    </div>
</section>
