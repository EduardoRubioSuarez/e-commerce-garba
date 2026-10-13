<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStoreSettingsRequest;
use App\Models\StoreSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', ['settings' => StoreSetting::current()]);
    }

    public function update(UpdateStoreSettingsRequest $request): RedirectResponse
    {
        StoreSetting::updateCurrent($request->validated(), $request->user());

        return redirect()->route('admin.settings.edit')->with('status', 'Configuración actualizada.');
    }
}
