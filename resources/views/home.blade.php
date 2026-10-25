@extends('layouts.app')

@section('title', 'Marketplace UMKM Kota Medan')

@section('content')
    <section class="hero">
        <div class="container hero-shell">
            <div class="hero-copy">
                <p class="eyebrow"><span class="dot"></span> Marketplace lokal Kota Medan</p>
                <h1 class="hero-title">Belanja langsung dari <em>UMKM</em> Kota Medan.</h1>
                <p class="hero-lead">Dari rendang rumahan dan kopi Gayo hingga ulos tenun tangan — temukan produk terbaik warga Medan, langsung dari toko mereka.</p>
                <div class="hero-actions">
                    <a href="{{ route('products.index') }}" class="btn btn-brand btn-lg">Jelajahi produk <i class="bi bi-arrow-up-right ms-1"></i></a>
                    <a href="{{ route('register') }}" class="btn btn-outline-brand btn-lg">Buka toko Anda</a>
                </div>
                <div class="hero-proof">
                    <div class="hero-avatars" aria-hidden="true">
                        <span>DS</span><span>KT</span><span>TD</span><span>+{{ max(0, $stats['sellers'] - 3) }}</span>
                    </div>
                    <div class="hero-proof-text">
                        <strong>UMKM pilihan dari berbagai penjuru Medan</strong>
                        <span>Dikelola langsung oleh pemilik toko</span>
                    </div>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <strong>{{ number_format($stats['stores'], 0, ',', '.') }}</strong>
                        <span>Toko aktif</span>
                    </div>
                    <div class="hero-stat">
                        <strong>{{ number_format($stats['products'], 0, ',', '.') }}</strong>
                        <span>Produk lokal</span>
                    </div>
                    <div class="hero-stat">
                        <strong>{{ number_format($stats['sellers'], 0, ',', '.') }}</strong>
                        <span>Penjual UMKM</span>
                    </div>
                </div>
            </div>

            <div class="hero-visual" aria-hidden="true">
                <div class="hero-img-main">
                    <img src="{{ asset('images/products/ulos-sadum.svg') }}" alt="">
                </div>
                <div class="hero-img-small one">
                    <img src="{{ asset('images/products/rendang-dapur-bu-sari.svg') }}" alt="">
                </div>
                <div class="hero-img-small two">
                    <img src="{{ asset('images/products/bika-ambon-original.svg') }}" alt="">
                </div>
                <div class="hero-float-card">
                    <span class="icon-box"><i class="bi bi-truck"></i></span>
                    <div>
                        <strong>Ongkir transparan</strong>
                        <span>Dihitung dengan RajaOngkir</span>
                    </div>
                </div>
                <div class="hero-badge-round">
                    <strong>100%</strong>
                    <span>UMKM<br>MEDAN</span>
                </div>
            </div>
        </div>
    </section>

    <section class="py-3 py-lg-4 bg-white border-bottom">
        <div class="container">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-3">
                        <span class="category-icon"><i class="bi bi-patch-check"></i></span>
                        <div>
                            <strong class="d-block" style="font-size:.82rem;">Produk lokal terkurasi</strong>
                            <span class="text-muted" style="font-size:.7rem;">Langsung dari pelaku UMKM Medan</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-3">
                        <span class="category-icon" style="background:var(--amber-soft);color:#a36309;"><i class="bi bi-truck"></i></span>
                        <div>
                            <strong class="d-block" style="font-size:.82rem;">Ongkir ke seluruh Indonesia</strong>
                            <span class="text-muted" style="font-size:.7rem;">Pilihan JNE, POS, dan TIKI</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-3">
                        <span class="category-icon" style="background:#e8f0fb;color:#2b5c9e;"><i class="bi bi-shield-check"></i></span>
                        <div>
                            <strong class="d-block" style="font-size:.82rem;">Pembayaran fleksibel</strong>
                            <span class="text-muted" style="font-size:.7rem;">Transfer, QRIS, atau COD</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="page-section">
        <div class="container">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Telusuri kategori</p>
                    <h2 class="section-title">Mulai dari yang Anda butuhkan</h2>
                </div>
                <a href="{{ route('products.index') }}" class="section-link">Lihat semua produk <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="category-grid">
                @foreach ($categories as $category)
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}" class="category-card">
                        <span class="category-icon"><i class="bi {{ $category->icon }}"></i></span>
                        <div>
                            <h3>{{ $category->name }}</h3>
                            <span>{{ $category->products_count }} produk</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="page-section pt-0">
        <div class="container">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Baru dari toko lokal</p>
                    <h2 class="section-title">Produk pilihan minggu ini</h2>
                    <p class="section-sub">Dikurasi dari toko-toko UMKM yang aktif di Sikitaya.</p>
                </div>
                <a href="{{ route('products.index', ['sort' => 'latest']) }}" class="section-link">Semua produk <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="product-grid">
                @foreach ($products as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </div>
    </section>

    <section class="page-section section-soft">
        <div class="container">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Kenali penjualnya</p>
                    <h2 class="section-title">Toko UMKM yang bisa Anda dukung</h2>
                </div>
                <a href="{{ route('stores.index') }}" class="section-link">Semua toko <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="store-grid">
                @foreach ($stores as $store)
                    <article class="store-card">
                        <div class="store-mark">
                            @if ($store->logo_url)
                                <img src="{{ $store->logo_url }}" alt="Logo {{ $store->name }}">
                            @else
                                {{ $store->initials }}
                            @endif
                        </div>
                        <div class="store-info">
                            <h3><a href="{{ route('stores.show', $store) }}">{{ $store->name }}</a></h3>
                            <p class="store-location"><i class="bi bi-geo-alt"></i> {{ $store->city_name }}</p>
                            <p class="store-desc">{{ $store->description }}</p>
                            <div class="store-counts">
                                <span><i class="bi bi-box-seam"></i> {{ $store->products_count }} produk</span>
                                <span><i class="bi bi-patch-check"></i> Toko aktif</span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="seller-cta pt-5">
        <div class="container">
            <div class="seller-cta-inner">
                <div>
                    <p class="eyebrow eyebrow-light">Punya usaha di Medan?</p>
                    <h2>Tumbuh bersama Sikitaya.</h2>
                    <p>Buka toko dalam hitungan menit, kelola produk, proses pesanan, dan pantau penjualan dari satu dashboard.</p>
                    <div class="cta-points">
                        <span><i class="bi bi-check-circle-fill"></i> Tanpa biaya pendaftaran</span>
                        <span><i class="bi bi-check-circle-fill"></i> Dashboard penjual</span>
                        <span><i class="bi bi-check-circle-fill"></i> Laporan Excel & PDF</span>
                    </div>
                    <a href="{{ route('register') }}" class="btn btn-amber mt-3">Buka toko sekarang <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
                <div class="seller-cta-visual">
                    <div class="cta-mockup">
                        <div class="cta-mockup-head"><span>Pesanan terbaru</span><span>Hari ini</span></div>
                        <div class="cta-mockup-row">
                            <img src="{{ asset('images/products/rendang-dapur-bu-sari.svg') }}" alt="">
                            <div><strong>Rendang Spesial</strong><span>SKT-261025-001</span></div>
                            <span class="amount">Dibayar</span>
                        </div>
                        <div class="cta-mockup-row">
                            <img src="{{ asset('images/products/kopi-arabika-gayo.svg') }}" alt="">
                            <div><strong>Arabika Gayo 250g</strong><span>SKT-261025-002</span></div>
                            <span class="amount">Dikirim</span>
                        </div>
                        <div class="cta-mockup-row">
                            <img src="{{ asset('images/products/ulos-sadum.svg') }}" alt="">
                            <div><strong>Ulos Sadum</strong><span>SKT-261025-003</span></div>
                            <span class="amount">Selesai</span>
                        </div>
                        <div class="cta-mockup-foot">Omzet bulan ini · Rp 1.240.000</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
