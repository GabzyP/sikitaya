@extends('layouts.app')

@section('title', 'Marketplace UMKM Kota Medan')

@section('content')
    <section class="hero home-hero">
        <div class="container hero-shell">
            <div class="hero-copy">
                <h1 class="hero-title">Belanja langsung dari <em>UMKM</em> Kota Medan.</h1>
                <p class="hero-lead">belanja nya kesini lah lek ku, sikit aya ya kan, biar tertolong umkm kita ini</p>
                <div class="hero-actions">
                    <a href="{{ route('products.index') }}" class="btn btn-brand btn-lg">Jelajahi produk <i class="bi bi-arrow-up-right ms-1"></i></a>
                    <a href="{{ route('register') }}" class="btn btn-outline-brand btn-lg">Buka toko Anda</a>
                </div>
                <div class="hero-proof">
                    <div class="hero-avatars" aria-hidden="true">
                        <span>DS</span><span>KT</span><span>TD</span><span>+{{ max(0, $sellerCount - 3) }}</span>
                    </div>
                    <div class="hero-proof-text">
                        <strong>UMKM pilihan dari berbagai penjuru Medan</strong>
                        <span>Dikelola langsung oleh pemilik toko</span>
                    </div>
                </div>
                <div class="hero-categories" aria-label="Kategori produk">
                    @foreach ($categoryLinks as $category)
                        <a href="{{ route('products.index', ['category' => $category['slug']]) }}" class="hero-category">
                            <span class="hero-category-icon"><i class="bi {{ $category['icon'] }}"></i></span>
                            <span>{{ $category['name'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="hero-visual" aria-hidden="true">
                <div class="hero-img-main">
                    <img src="{{ asset('images/products/ulos-sadum.jpg') }}" alt="">
                </div>
                <div class="hero-img-small one">
                    <img src="{{ asset('images/products/rendang-dapur-bu-sari.jpg') }}" alt="">
                </div>
                <div class="hero-img-small two">
                    <img src="{{ asset('images/products/bika-ambon-original.jpg') }}" alt="">
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
@endsection
