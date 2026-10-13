<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Models\AuditEntry;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\CouponReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CouponController extends Controller
{
    public function index(): View
    {
        return view('admin.coupons.index', ['coupons' => Coupon::query()->with('products')->orderByDesc('id')->paginate(20), 'settings' => StoreSetting::current()]);
    }

    public function create(): View
    {
        return $this->form(new Coupon);
    }

    public function edit(Coupon $coupon): View
    {
        return $this->form($coupon);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $this->persist(new Coupon, $request);

        return redirect()->route('admin.coupons.index')->with('success', 'Cupón creado.');
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $this->persist($coupon, $request);

        return redirect()->route('admin.coupons.index')->with('success', 'Cupón actualizado.');
    }

    private function form(Coupon $coupon): View
    {
        return view('admin.coupons.form', ['coupon' => $coupon->load('products'), 'products' => Product::query()->orderBy('name')->get(['id', 'name']), 'settings' => StoreSetting::current()]);
    }

    private function persist(Coupon $coupon, StoreCouponRequest $request): void
    {
        try {
            DB::transaction(function () use ($coupon, $request): void {
                StoreSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
                $current = $coupon->exists ? Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail() : $coupon;
                $isNew = ! $current->exists;
                $before = $isNew ? [] : $current->attributesToArray();
                $before['product_ids'] = $isNew ? [] : $current->products()->orderBy('products.id')->pluck('products.id')->all();
                $current->fill($request->attributesForPersistence());
                if ($current->exists && $current->max_uses < app(CouponReservationService::class)->occupied($current)) {
                    throw ValidationException::withMessages(['max_uses' => 'El límite no puede ser menor que los usos consumidos y reservados.']);
                }
                if (! $current->save()) {
                    throw new RuntimeException('Coupon could not be persisted.');
                }
                $ids = $current->scope === CouponScope::Selected ? $request->validated('product_ids', []) : [];
                $current->products()->sync($ids);
                $after = $current->attributesToArray();
                $after['product_ids'] = $current->products()->orderBy('products.id')->pluck('products.id')->all();
                $audit = AuditEntry::create(['actor_id' => $request->user()->id, 'action' => $isNew ? 'coupon.created' : 'coupon.updated',
                    'entity_type' => 'coupon', 'entity_id' => $current->id, 'changes' => ['old' => $before, 'new' => $after]]);
                if (! $audit->exists) {
                    throw new RuntimeException('Coupon audit could not be persisted.');
                }
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Ese código ya pertenece a un cupón, aunque esté desactivado.']);
        }
    }
}
