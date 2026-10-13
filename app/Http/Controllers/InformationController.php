<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InformationController extends Controller
{
    public function show(Request $request): Response|JsonResponse
    {
        $setting = StoreSetting::current();
        $data = [
            'pickup' => ['address' => (string) $setting->pickup_address, 'hours' => (string) $setting->pickup_hours],
            'contact' => ['email' => (string) $setting->contact_email, 'phone' => (string) $setting->contact_phone],
            'is_demo' => $setting->is_demo || config('store.information.is_demo', true),
            'terms' => (string) ($setting->terms ?? config('store.information.terms')),
            'privacy' => (string) ($setting->privacy ?? config('store.information.privacy')),
            'returns' => (string) ($setting->returns ?? config('store.information.returns')),
        ];
        if ($request->expectsJson()) {
            return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
        }

        return response()->view('store.information', compact('data'))->header('Cache-Control', 'private, no-store');
    }
}
