@extends('layouts.app')

@section('title', 'Toko UMKM Kota Medan')

@section('content')
    <section class="page-banner">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Toko</li>
                </ol>
            </nav>
            <h1>Kenali penjual di balik produk</h1>
            <p>Setiap toko dikelola langsung oleh pelaku UMKM Kota Medan. Kunjungi toko, kenali ceritanya, dan belanja produk unggulan mereka.</p>
        </div>
    </section>

    <section class="page-section">
        <div class="container">
            <div class="catalog-toolbar">
                <p class="result-count mb-0">
                    <strong>{{ $stores->total() }}</strong> toko aktif terdaftar di Sikitaya
                </p>
                <form action="{{ route('stores.index') }}" method="GET" class="nav-search" style="min-width:240px;" role="search">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama toko..." aria-label="Cari toko">
                    <button type="submit" class="visually-hidden">Cari</button>
                </form>
            </div>

            @if ($stores->isEmpty())
                <div class="empty-state">
                    <span class="empty-icon"><i class="bi bi-shop"></i></span>
                    <h2>Belum ada toko yang cocok</h2>
                    <p>Coba gunakan kata kunci lain, atau jelajahi seluruh toko UMKM yang ada di Sikitaya.</p>
                    <a href="{{ route('stores.index') }}" class="btn btn-brand">Lihat semua toko</a>
                </div>
            @else
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
                                <p class="store-location"><i class="bi bi-geo-alt"></i> {{ $store->city_name }}, {{ $store->province_name }}</p>
                                <p class="store-desc">{{ $store->description ?: 'Toko UMKM Kota Medan yang dikelola langsung oleh pemiliknya.' }}</p>
                                <div class="store-counts">
                                    <span><i class="bi bi-box-seam"></i> {{ $store->products_count }} produk</span>
                                    <span><i class="bi bi-patch-check"></i> Toko aktif</span>
                                </div>
                                <a href="{{ route('stores.show', $store) }}" class="btn btn-outline-brand btn-sm mt-3">Kunjungi toko <i class="bi bi-arrow-right ms-1"></i></a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-4 d-flex justify-content-center">
                    {{ $stores->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
