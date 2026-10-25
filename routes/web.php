<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Buyer;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Seller;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/produk', [CatalogController::class, 'index'])->name('products.index');
Route::get('/produk/{product:slug}', [CatalogController::class, 'show'])->name('products.show');
Route::get('/toko', [StorefrontController::class, 'index'])->name('stores.index');
Route::get('/toko/{store:slug}', [StorefrontController::class, 'show'])->name('stores.show');
Route::get('/api/ongkir', [ShippingController::class, 'cost'])->name('shipping.cost');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
});

Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/keranjang', [Buyer\CartController::class, 'index'])->name('cart.index');
    Route::post('/keranjang', [Buyer\CartController::class, 'store'])->name('cart.store');
    Route::patch('/keranjang/{cartItem}', [Buyer\CartController::class, 'update'])->name('cart.update');
    Route::delete('/keranjang/{cartItem}', [Buyer\CartController::class, 'destroy'])->name('cart.destroy');
    Route::delete('/keranjang', [Buyer\CartController::class, 'clear'])->name('cart.clear');

    Route::get('/checkout', [Buyer\CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [Buyer\CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/pesanan', [Buyer\OrderController::class, 'index'])->name('orders.index');
    Route::get('/pesanan/{order}', [Buyer\OrderController::class, 'show'])->name('orders.show');
    Route::post('/pesanan/{order}/bukti-bayar', [Buyer\OrderController::class, 'uploadProof'])->name('orders.proof');
    Route::post('/pesanan/{order}/selesai', [Buyer\OrderController::class, 'complete'])->name('orders.complete');
});

Route::prefix('penjual')->name('seller.')->middleware(['auth', 'active', 'role:seller'])->group(function () {
    Route::get('/toko', [Seller\StoreProfileController::class, 'edit'])->name('store.edit');
    Route::put('/toko', [Seller\StoreProfileController::class, 'update'])->name('store.update');

    Route::middleware('seller.store')->group(function () {
        Route::get('/', Seller\DashboardController::class)->name('dashboard');

        Route::get('/produk', [Seller\ProductController::class, 'index'])->name('products.index');
        Route::get('/produk/tambah', [Seller\ProductController::class, 'create'])->name('products.create');
        Route::post('/produk', [Seller\ProductController::class, 'store'])->name('products.store');
        Route::get('/produk/{product}/ubah', [Seller\ProductController::class, 'edit'])->name('products.edit');
        Route::put('/produk/{product}', [Seller\ProductController::class, 'update'])->name('products.update');
        Route::delete('/produk/{product}', [Seller\ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/produk/{product}/status', [Seller\ProductController::class, 'toggle'])->name('products.toggle');

        Route::get('/pesanan', [Seller\OrderController::class, 'index'])->name('orders.index');
        Route::get('/pesanan/{order}', [Seller\OrderController::class, 'show'])->name('orders.show');
        Route::post('/pesanan/{order}/konfirmasi-pembayaran', [Seller\OrderController::class, 'confirmPayment'])->name('orders.confirm');
        Route::post('/pesanan/{order}/kirim', [Seller\OrderController::class, 'ship'])->name('orders.ship');

        Route::get('/laporan', [Seller\ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/excel', [Seller\ReportController::class, 'excel'])->name('reports.excel');
        Route::get('/laporan/pdf', [Seller\ReportController::class, 'pdf'])->name('reports.pdf');
    });
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'role:admin'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/pengguna', [Admin\UserController::class, 'index'])->name('users.index');
    Route::post('/pengguna/{user}/status', [Admin\UserController::class, 'toggle'])->name('users.toggle');

    Route::get('/toko', [Admin\StoreController::class, 'index'])->name('stores.index');
    Route::post('/toko/{store}/status', [Admin\StoreController::class, 'toggle'])->name('stores.toggle');

    Route::get('/kategori', [Admin\CategoryController::class, 'index'])->name('categories.index');
    Route::get('/kategori/tambah', [Admin\CategoryController::class, 'create'])->name('categories.create');
    Route::post('/kategori', [Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::get('/kategori/{category}/ubah', [Admin\CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/kategori/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/kategori/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/pesanan', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/pesanan/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');

    Route::get('/laporan', [Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('/laporan/excel', [Admin\ReportController::class, 'excel'])->name('reports.excel');
    Route::get('/laporan/pdf', [Admin\ReportController::class, 'pdf'])->name('reports.pdf');
});
