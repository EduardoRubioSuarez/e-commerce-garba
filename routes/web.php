<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\InstallerController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\OrderNotificationController;
use App\Http\Controllers\Admin\PaymentRecoveryController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductPhotoController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\AdminSecondFactorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CouponValidationController;
use App\Http\Controllers\EmbeddedCheckoutController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\InstallerActivationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProductController as StoreProductController;
use App\Http\Controllers\StripeWebhookController;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

Route::model('product', Product::class);
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::post('/checkout/cupon', CouponValidationController::class)->middleware('throttle:coupon-validation')->name('coupons.validate');
Route::get('/activar/{token}', [InstallerActivationController::class, 'create'])->middleware('throttle:20,1')->name('installer.activate');
Route::post('/activar', [InstallerActivationController::class, 'store'])->middleware('throttle:10,1')->name('installer.activate.store');
Route::get('/recuperar-contrasena', [PasswordResetController::class, 'create'])->name('password.request');
Route::post('/recuperar-contrasena', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
Route::get('/restablecer-contrasena/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
Route::post('/restablecer-contrasena', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
Route::middleware(['auth', 'active.account', 'cache.headers:private;no_store'])->group(function (): void {
    Route::get('/segundo-factor', [AdminSecondFactorController::class, 'create'])->name('admin.second-factor');
    Route::post('/segundo-factor', [AdminSecondFactorController::class, 'verify'])->middleware('throttle:10,1')->block(10, 5)->name('admin.second-factor.verify');
    Route::post('/segundo-factor/reenviar', [AdminSecondFactorController::class, 'resend'])->middleware('throttle:5,1')->block(10, 5)->name('admin.second-factor.resend');
    Route::post('/segundo-factor/olvidar-dispositivos', [AdminSecondFactorController::class, 'forgetDevices'])->middleware('throttle:5,1')->name('admin.second-factor.forget');
});

Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('/catalogo', [CatalogController::class, 'index'])->name('store.catalog');
Route::get('/productos/{slug}', [StoreProductController::class, 'show'])->name('store.product');
Route::get('/carrito', [CartController::class, 'show'])->name('cart.show');
Route::put('/carrito', [CartController::class, 'update'])->name('cart.update');
Route::get('/informacion', [InformationController::class, 'show'])->name('store.information');
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout/revision', [CheckoutController::class, 'review'])->block(60, 10)->name('checkout.review');
Route::get('/checkout/revision', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
Route::post('/checkout', [CheckoutController::class, 'start'])->block(60, 10)->name('checkout.start');
Route::get('/pedidos/{order:reference}', [OrderController::class, 'show'])->whereUuid('order')->name('orders.show');
Route::post('/pedidos/{order:reference}/formulario-pago', EmbeddedCheckoutController::class)->whereUuid('order')
    ->middleware('throttle:20,1,embedded-checkout:')->block(10, 5)->name('orders.embedded-checkout');
Route::post('/pedidos/{order:reference}/pago', [CheckoutController::class, 'resume'])->whereUuid('order')->block(60, 10)->name('orders.resume');
Route::post('/pedidos/{order:reference}/nueva-compra', [CheckoutController::class, 'newPurchase'])->whereUuid('order')->block(60, 10)->name('orders.new-purchase');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/admin', fn () => response()->view('admin.dashboard')->header('Cache-Control', 'private, no-store'))
    ->middleware(['auth', 'active.account', 'administrator'])->name('admin.dashboard');

Route::prefix('admin')->name('admin.')
    ->middleware(['auth', 'active.account', 'administrator', 'cache.headers:private;no_store'])
    ->group(function (): void {
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order:reference}', [AdminOrderController::class, 'show'])->whereUuid('order')->name('orders.show');
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{notification}/retry', [NotificationController::class, 'retry'])->middleware('throttle:10,1')->name('notifications.retry');
        Route::post('payments/{attempt}/recover', [PaymentRecoveryController::class, 'store'])->middleware('throttle:10,1')->name('payment-recovery.store');
        Route::resource('coupons', CouponController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::get('installers', [InstallerController::class, 'index'])->name('installers.index');
        Route::post('installers', [InstallerController::class, 'store'])->middleware('throttle:10,1')->name('installers.store');
        Route::post('installers/{installer}/invitation', [InstallerController::class, 'resend'])->middleware('throttle:5,1')->name('installers.resend');
        Route::post('installers/{installer}/suspend', [InstallerController::class, 'suspend'])->middleware('throttle:10,1')->name('installers.suspend');
        Route::post('installers/{installer}/reactivate', [InstallerController::class, 'reactivate'])->middleware('throttle:10,1')->name('installers.reactivate');
        Route::delete('installers/{installer}', [InstallerController::class, 'destroy'])->middleware('throttle:10,1')->name('installers.destroy');
        Route::post('orders/{order:reference}/pickup-notification', [OrderNotificationController::class, 'store'])->whereUuid('order')->name('order-notifications.store');
        Route::post('orders/{order:reference}/pickup-notification/resend', [OrderNotificationController::class, 'resend'])->whereUuid('order')->name('order-notifications.resend');
        Route::get('pricing', [PricingController::class, 'edit'])->name('pricing.edit');
        Route::put('pricing', [PricingController::class, 'update'])->name('pricing.update');
        Route::put('products/{product}/pricing', [PricingController::class, 'update'])->name('product-pricing.update');
        Route::resource('products', ProductController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update']);
        Route::post('products/{product}/photos', [ProductPhotoController::class, 'store'])->name('product-photos.store');
        Route::put('products/{product}/photos/{photo}', [ProductPhotoController::class, 'update'])->scopeBindings()->name('product-photos.update');
    });
