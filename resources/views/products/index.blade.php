@extends('layouts.app')

@php
    $queryBase = array_filter([
        'q' => $filters['q'] ?? null,
        'category' => $filters['category'] ?? null,
        'store' => $filters['store'] ?? null,
        'sort' => $filters['sort'] ?? null,
    ], fn ($value) => filled($value));

    $isGuest = auth()->guest();

    if ($isGuest && filled($filters['q'] ?? null)) {
        $pageTitle = 'Hasil untuk “'.$filters['q'].'”';
    } elseif ($activeCategory) {
        $pageTitle = 'Kategori '.$activeCategory->name;
    } elseif ($activeStore) {
        $pageTitle = 'Produk '.$activeStore->name;
    } elseif ($isGuest) {
        $pageTitle = 'Produk pilihan minggu ini';
    } else {
        $pageTitle = 'Produk UMKM Kota Medan';
    }

    $sortOptions = [
        'latest' => 'Terbaru',
        'price_asc' => 'Harga terendah',
        'price_desc' => 'Harga tertinggi',
        'name' => 'Nama A-Z',
    ];

    $activeSort = $filters['sort'] ?? 'latest';
@endphp

@section('title', $pageTitle)

@section('content')
    <section class="page-banner">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                    @if ($activeStore)
                        <li class="breadcrumb-item"><a href="{{ route('stores.index') }}">Toko</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $activeStore->name }}</li>
                    @elseif ($activeCategory)
                        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Produk</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $activeCategory->name }}</li>
                    @else
                        <li class="breadcrumb-item active" aria-current="page">Produk</li>
                    @endif
                </ol>
            </nav>
            @if ($isGuest)
                <span class="guest-note"><i class="bi bi-eye"></i> Mode tamu · katalog terbatas</span>
            @endif
            <h1>{{ $pageTitle }}</h1>
            <p>
                @if ($isGuest)
                    Kurasi produk terbaik dari toko UMKM Kota Medan. Masuk untuk membuka seluruh katalog, filter lengkap, dan melihat lebih jauh.
                @elseif ($activeStore)
                    Jelajahi produk dari {{ $activeStore->name }}. Toko ini berlokasi di {{ $activeStore->city_name }} dan dikelola langsung oleh pemiliknya.
                @elseif ($activeCategory)
                    {{ $activeCategory->description ?: 'Temukan produk '.$activeCategory->name.' pilihan dari UMKM Kota Medan dan sekitarnya.' }}
                @else
                    Dari kuliner rumahan hingga kriya tenun tangan, temukan produk asli warga Medan dan dukung usaha lokal.
                @endif
            </p>
        </div>
    </section>

    <section class="page-section">
        <div class="container">
            @if ($isGuest)
                <div class="section-head">
                    <div>
                        <p class="eyebrow">Pilihan minggu ini</p>
                        <h2 class="section-title">Produk unggulan dari UMKM Medan</h2>
                        <p class="section-sub">Sebagian produk pilihan yang bisa Anda dukung. Masuk untuk melihat katalog lengkap dan mulai berbelanja.</p>
                    </div>
                    <form action="{{ route('products.index') }}" method="GET" class="nav-search" role="search">
                        <i class="bi bi-search"></i>
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari produk..." aria-label="Cari produk">
                        <button type="submit" class="visually-hidden">Cari</button>
                    </form>
                </div>

                @if ($products->isEmpty())
                    <div class="empty-state">
                        <span class="empty-icon"><i class="bi bi-search"></i></span>
                        <h2>Produk tidak ditemukan</h2>
                        <p>Coba kata kunci lain, atau masuk untuk melihat seluruh produk UMKM Kota Medan.</p>
                    </div>
                @else
                    <div class="catalog-toolbar">
                        <p class="result-count mb-0">
                            Menampilkan <strong>{{ $products->count() }}</strong> dari <strong>{{ $products->total() }}</strong> produk
                        </p>
                        <span class="chip"><i class="bi bi-shop"></i> Dikelola langsung pemilik toko</span>
                    </div>

                    <div class="product-grid">
                        @foreach ($products as $product)
                            @include('partials.product-card', ['product' => $product])
                        @endforeach
                    </div>
                @endif

                @include('partials.guest-gate', [
                    'title' => 'Masuk untuk melihat lebih jauh',
                    'message' => 'Masuk atau daftar untuk membuka seluruh katalog produk, filter kategori, dan halaman toko lengkap Sikitaya.',
                ])
            @else
                <div class="row g-4">
                    <div class="col-lg-3">
                        <aside class="filter-panel">
                            <h2>Kategori</h2>
                            <div class="filter-group">
                                <a class="filter-link {{ empty($filters['category'] ?? null) ? 'active' : '' }}"
                                   href="{{ route('products.index', array_filter(array_merge($queryBase, ['category' => null]))) }}">
                                    Semua kategori
                                    <span>{{ number_format($categories->sum('products_count'), 0, ',', '.') }}</span>
                                </a>
                                @foreach ($categories as $category)
                                    <a class="filter-link {{ ($filters['category'] ?? null) === $category->slug ? 'active' : '' }}"
                                       href="{{ route('products.index', array_filter(array_merge($queryBase, ['category' => $category->slug]))) }}">
                                        {{ $category->name }}
                                        <span>{{ $category->products_count }}</span>
                                    </a>
                                @endforeach
                            </div>

                            @if ($activeStore)
                                <div class="filter-group">
                                    <div class="filter-title">Toko</div>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="avatar-store">
                                            @if ($activeStore->logo_url)
                                                <img src="{{ $activeStore->logo_url }}" alt="{{ $activeStore->name }}">
                                            @else
                                                {{ $activeStore->initials }}
                                            @endif
                                        </span>
                                        <div class="min-w-0">
                                            <strong class="d-block" style="font-size:.76rem;">{{ $activeStore->name }}</strong>
                                            <span class="text-muted" style="font-size:.66rem;">{{ $activeStore->city_name }}</span>
                                        </div>
                                    </div>
                                    <a href="{{ route('stores.show', $activeStore) }}" class="filter-link">Lihat profil toko</a>
                                    <a href="{{ route('products.index', array_filter(array_merge($queryBase, ['store' => null]))) }}" class="filter-link">Hapus filter toko</a>
                                </div>
                            @endif

                            <div class="filter-group">
                                <div class="filter-title">Urutkan</div>
                                @foreach ($sortOptions as $value => $label)
                                    <a class="filter-link {{ $activeSort === $value ? 'active' : '' }}"
                                       href="{{ route('products.index', array_filter(array_merge($queryBase, ['sort' => $value]))) }}">
                                        {{ $label }}
                                    </a>
                                @endforeach
                            </div>

                            <div class="filter-group">
                                <div class="filter-title">Belanja dengan tenang</div>
                                <p class="mb-0" style="font-size:.7rem;line-height:1.6;color:var(--muted);">
                                    Setiap pesanan diproses langsung oleh penjual. Ongkir dihitung transparan saat checkout.
                                </p>
                            </div>
                        </aside>
                    </div>

                    <div class="col-lg-9">
                        <div class="catalog-toolbar">
                            <p class="result-count mb-0">
                                Menampilkan
                                <strong>{{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }}</strong>
                                dari <strong>{{ $products->total() }}</strong> produk
                            </p>
                            <form action="{{ route('products.index') }}" method="GET" class="d-flex gap-2" role="search">
                                @if ($filters['category'] ?? null)
                                    <input type="hidden" name="category" value="{{ $filters['category'] }}">
                                @endif
                                @if ($filters['store'] ?? null)
                                    <input type="hidden" name="store" value="{{ $filters['store'] }}">
                                @endif
                                @if ($activeSort !== 'latest')
                                    <input type="hidden" name="sort" value="{{ $activeSort }}">
                                @endif
                                <div class="nav-search" style="min-width:220px;">
                                    <i class="bi bi-search"></i>
                                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari produk..." aria-label="Cari produk">
                                </div>
                                <button type="submit" class="btn btn-brand btn-sm">Cari</button>
                            </form>
                        </div>

                        @if (filled($filters['q'] ?? null) || $activeCategory || $activeStore)
                            <div class="active-filters">
                                @if (filled($filters['q'] ?? null))
                                    <span class="chip">
                                        <i class="bi bi-search"></i> "{{ $filters['q'] }}"
                                        <a href="{{ route('products.index', array_filter(array_merge($queryBase, ['q' => null]))) }}" aria-label="Hapus pencarian"><i class="bi bi-x-lg"></i></a>
                                    </span>
                                @endif
                                @if ($activeCategory)
                                    <span class="chip">
                                        <i class="bi bi-tag"></i> {{ $activeCategory->name }}
                                        <a href="{{ route('products.index', array_filter(array_merge($queryBase, ['category' => null]))) }}" aria-label="Hapus filter kategori"><i class="bi bi-x-lg"></i></a>
                                    </span>
                                @endif
                                @if ($activeStore)
                                    <span class="chip">
                                        <i class="bi bi-shop"></i> {{ $activeStore->name }}
                                        <a href="{{ route('products.index', array_filter(array_merge($queryBase, ['store' => null]))) }}" aria-label="Hapus filter toko"><i class="bi bi-x-lg"></i></a>
                                    </span>
                                @endif
                            </div>
                        @endif

                        @if ($products->isEmpty())
                            <div class="empty-state">
                                <span class="empty-icon"><i class="bi bi-search"></i></span>
                                <h2>Produk tidak ditemukan</h2>
                                <p>Coba gunakan kata kunci lain, pilih kategori berbeda, atau hapus filter yang sedang aktif.</p>
                                <a href="{{ route('products.index') }}" class="btn btn-brand">Lihat semua produk</a>
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
            @endif
        </div>
    </section>
@endsection
