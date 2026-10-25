@extends('layouts.app')

@section('title', $store->name)
@section('meta_description', Str::limit(strip_tags($store->description), 150))

@section('content')
    <section class="store-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('stores.index') }}">Toko</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $store->name }}</li>
                </ol>
            </nav>

            <div class="store-hero-main">
                <div class="store-hero-mark">
                    @if ($store->logo_url)
                        <img src="{{ $store->logo_url }}" alt="Logo {{ $store->name }}">
                    @else
                        {{ $store->initials }}
                    @endif
                </div>
                <div class="store-hero-copy">
                    <span class="store-verified"><i class="bi bi-patch-check-fill"></i> Penjual terverifikasi Sikitaya</span>
                    <h1>{{ $store->name }}</h1>
                    <p>{{ $store->description ?: 'Toko UMKM Kota Medan yang menjual produk lokal pilihan.' }}</p>
                    <div class="store-hero-meta">
                        <span><i class="bi bi-geo-alt"></i> {{ $store->city_name }}, {{ $store->province_name }}</span>
                        <span><i class="bi bi-box-seam"></i> {{ $store->products_count }} produk aktif</span>
                        <span><i class="bi bi-calendar3"></i> Bergabung {{ $store->created_at->locale('id')->translatedFormat('F Y') }}</span>
                    </div>
                </div>
                <div class="store-hero-actions">
                    <a href="#produk-toko" class="btn btn-amber">Lihat produk <i class="bi bi-arrow-down-right ms-1"></i></a>
                    @if ($store->phone)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $store->phone) }}" target="_blank" rel="noopener" class="btn btn-light-brand">
                            <i class="bi bi-whatsapp me-1"></i> Hubungi
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="page-section" id="produk-toko">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-3">
                    <aside class="filter-panel">
                        <h2>Tentang toko</h2>
                        <div class="filter-group">
                            <div class="d-flex align-items-start gap-2 mb-3">
                                <i class="bi bi-geo-alt text-brand mt-1"></i>
                                <div>
                                    <strong class="d-block" style="font-size:.76rem;">Lokasi toko</strong>
                                    <span class="text-muted" style="font-size:.7rem;line-height:1.5;display:block;">
                                        {{ $store->address ?: 'Alamat belum dilengkapi' }}<br>
                                        {{ $store->city_name }}, {{ $store->province_name }}
                                    </span>
                                </div>
                            </div>
                            @if ($store->phone)
                                <div class="d-flex align-items-start gap-2 mb-3">
                                    <i class="bi bi-telephone text-brand mt-1"></i>
                                    <div>
                                        <strong class="d-block" style="font-size:.76rem;">Kontak penjual</strong>
                                        <span class="text-muted" style="font-size:.7rem;">{{ $store->phone }}</span>
                                    </div>
                                </div>
                            @endif
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-box-seam text-brand mt-1"></i>
                                <div>
                                    <strong class="d-block" style="font-size:.76rem;">Katalog toko</strong>
                                    <span class="text-muted" style="font-size:.7rem;">{{ $store->products_count }} produk aktif</span>
                                </div>
                            </div>
                        </div>
                        <div class="filter-group">
                            <div class="filter-title">Belanja aman</div>
                            <p class="mb-0" style="font-size:.7rem;line-height:1.6;color:var(--muted);">
                                Pembayaran diverifikasi penjual sebelum pesanan dikirim. Simpan bukti transfer Anda hingga pesanan selesai.
                            </p>
                        </div>
                        <a href="{{ route('products.index', ['store' => $store->slug]) }}" class="btn btn-outline-brand btn-sm w-100">Beli lewat katalog <i class="bi bi-arrow-right ms-1"></i></a>
                    </aside>
                </div>

                <div class="col-lg-9">
                    <div class="catalog-toolbar">
                        <p class="result-count mb-0">
                            Produk dari <strong>{{ $store->name }}</strong>
                        </p>
                        <span class="chip"><i class="bi bi-shield-check"></i> Dikelola pemilik toko</span>
                    </div>

                    @if ($products->isEmpty())
                        <div class="empty-state">
                            <span class="empty-icon"><i class="bi bi-box"></i></span>
                            <h2>Belum ada produk</h2>
                            <p>Toko ini belum menambahkan produk yang aktif. Silakan kembali lagi nanti.</p>
                            <a href="{{ route('stores.index') }}" class="btn btn-brand">Jelajahi toko lain</a>
                        </div>
                    @else
                        <div class="product-grid">
                            @foreach ($products as $product)
                                @include('partials.product-card', ['product' => $product])
                            @endforeach
                        </div>

                        <div class="mt-4 d-flex justify-content-center">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
