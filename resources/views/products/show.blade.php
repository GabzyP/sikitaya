@extends('layouts.app')

@section('title', $product->name)
@section('meta_description', Str::limit(strip_tags($product->description), 150))

@section('content')
    <section class="page-banner page-banner-light">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Produk</a></li>
                    @if ($product->category)
                        <li class="breadcrumb-item"><a href="{{ route('products.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a></li>
                    @endif
                    <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($product->name, 45) }}</li>
                </ol>
            </nav>
            <h1>{{ $product->name }}</h1>
            <p>Dijual oleh {{ $product->store->name }} · {{ $product->store->city_name }}, {{ $product->store->province_name }}</p>
        </div>
    </section>

    <section class="page-section">
        <div class="container">
            <div class="product-detail">
                <div class="product-detail-img">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                </div>

                <div class="product-detail-copy">
                    <a href="{{ route('stores.show', $product->store) }}" class="product-store">
                        <i class="bi bi-shop"></i> {{ $product->store->name }}
                        <i class="bi bi-patch-check-fill text-brand ms-1"></i>
                    </a>

                    <h1>{{ $product->name }}</h1>
                    <p class="price-large mb-0">{{ rupiah($product->price) }}</p>

                    <div class="detail-meta">
                        @if ($product->category)
                            <span><i class="bi bi-tag"></i> {{ $product->category->name }}</span>
                        @endif
                        <span><i class="bi bi-box-seam"></i> Stok {{ $product->stock }}</span>
                        <span><i class="bi bi-speedometer2"></i> Berat {{ berat($product->weight) }}</span>
                        <span><i class="bi bi-geo-alt"></i> {{ $product->store->city_name }}</span>
                    </div>

                    <p class="product-description">
                        {{ $product->description ?: 'Penjual belum menambahkan deskripsi untuk produk ini. Silakan hubungi toko untuk informasi lebih lanjut.' }}
                    </p>

                    <div class="buy-box">
                        @if ($product->stock > 0)
                            <div class="stock-line">
                                <span>Tersedia <strong>{{ $product->stock }} item</strong></span>
                                <span class="text-muted">{{ berat($product->weight) }} / item</span>
                            </div>

                            @auth
                                <form action="{{ route('cart.store') }}" method="POST" class="d-flex flex-wrap align-items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <div class="qty-control">
                                        <button type="button" data-qty-minus aria-label="Kurangi jumlah"><i class="bi bi-dash"></i></button>
                                        <input type="number" name="quantity" value="{{ max(1, (int) old('quantity', 1)) }}" min="1" max="{{ min(99, $product->stock) }}" aria-label="Jumlah produk">
                                        <button type="button" data-qty-plus aria-label="Tambah jumlah"><i class="bi bi-plus"></i></button>
                                    </div>
                                    <button type="submit" class="btn btn-outline-brand flex-grow-1">
                                        <i class="bi bi-bag-plus me-1"></i> Tambah ke keranjang
                                    </button>
                                    <button type="submit" class="btn btn-brand flex-grow-1" formaction="{{ route('checkout.direct') }}">
                                        <i class="bi bi-lightning-charge me-1"></i> Checkout langsung
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="btn btn-brand w-100">
                                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk untuk membeli
                                </a>
                                <div class="info-note">
                                    <i class="bi bi-lock"></i>
                                    <span>Masuk atau daftar akun terlebih dahulu untuk checkout atau menambahkan produk ke keranjang dan melihat pesanan Anda.</span>
                                </div>
                            @endauth

                            <div class="info-note">
                                <i class="bi bi-truck"></i>
                                <span>Bisa checkout langsung tanpa menyimpan produk di keranjang. Ongkir dihitung otomatis saat checkout berdasarkan kota tujuan dan berat pesanan. Pembayaran dapat melalui transfer, QRIS, atau COD.</span>
                            </div>
                        @else
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Stok produk ini sedang habis. Silakan kembali lagi nanti atau jelajahi produk lain dari toko ini.
                            </div>
                        @endif
                    </div>

                    <div class="store-mini-card">
                        <span class="store-mark">
                            @if ($product->store->logo_url)
                                <img src="{{ $product->store->logo_url }}" alt="Logo {{ $product->store->name }}">
                            @else
                                {{ $product->store->initials }}
                            @endif
                        </span>
                        <div class="flex-grow-1">
                            <strong>{{ $product->store->name }}</strong>
                            <span><i class="bi bi-geo-alt"></i> {{ $product->store->city_name }} · <i class="bi bi-box-seam"></i> {{ $product->store->products_count ?? $product->store->products()->where('is_active', true)->count() }} produk</span>
                        </div>
                        <a href="{{ route('stores.show', $product->store) }}" class="btn btn-outline-brand btn-sm">Kunjungi toko</a>
                    </div>
                </div>
            </div>

            @if ($related->isNotEmpty())
                <div class="section-head mt-5">
                    <div>
                        <p class="eyebrow">Mungkin Anda suka</p>
                        <h2 class="section-title">Produk serupa dari UMKM lain</h2>
                    </div>
                    <a href="{{ route('products.index') }}" class="section-link">Semua produk <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="product-grid">
                    @foreach ($related as $relatedProduct)
                        @include('partials.product-card', ['product' => $relatedProduct])
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
