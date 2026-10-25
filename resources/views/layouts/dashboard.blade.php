<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Sikitaya</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="dashboard-body">
    @php($isSeller = auth()->user()->isSeller())

    <div class="dashboard-shell">
        <aside class="dashboard-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="dashboardSidebar" aria-label="Navigasi dashboard">
            <div class="sidebar-inner">
                <div class="sidebar-head">
                    <a href="{{ route('home') }}" class="brand brand-light text-decoration-none">
                        <span class="brand-mark">S</span>
                        <span class="brand-text">
                            <span class="brand-name">Sikitaya</span>
                            <span class="brand-tag">{{ $isSeller ? 'RUANG PENJUAL' : 'PANEL ADMIN' }}</span>
                        </span>
                    </a>
                    <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#dashboardSidebar" aria-label="Tutup"></button>
                </div>

                <div class="sidebar-store">
                    @if ($isSeller && auth()->user()->store)
                        @if (auth()->user()->store->logo_url)
                            <img src="{{ auth()->user()->store->logo_url }}" alt="{{ auth()->user()->store->name }}" class="sidebar-store-logo">
                        @else
                            <span class="sidebar-store-logo sidebar-store-initial">{{ auth()->user()->store->initials }}</span>
                        @endif
                        <div>
                            <strong>{{ auth()->user()->store->name }}</strong>
                            <span><i class="bi bi-geo-alt"></i> {{ auth()->user()->store->city_name }}</span>
                        </div>
                    @else
                        <span class="sidebar-store-logo sidebar-store-initial"><i class="bi bi-shield-check"></i></span>
                        <div>
                            <strong>Admin Platform</strong>
                            <span>Sikitaya Kota Medan</span>
                        </div>
                    @endif
                </div>

                <nav class="sidebar-nav">
                    <p class="sidebar-label">Menu utama</p>

                    @if ($isSeller)
                        <a class="sidebar-link {{ request()->routeIs('seller.dashboard') ? 'active' : '' }}" href="{{ route('seller.dashboard') }}"><i class="bi bi-grid-1x2"></i> Ringkasan</a>
                        <a class="sidebar-link {{ request()->routeIs('seller.orders.*') ? 'active' : '' }}" href="{{ route('seller.orders.index') }}"><i class="bi bi-receipt"></i> Pesanan</a>
                        <a class="sidebar-link {{ request()->routeIs('seller.products.*') ? 'active' : '' }}" href="{{ route('seller.products.index') }}"><i class="bi bi-box-seam"></i> Produk</a>
                        <a class="sidebar-link {{ request()->routeIs('seller.reports.*') ? 'active' : '' }}" href="{{ route('seller.reports.index') }}"><i class="bi bi-bar-chart-line"></i> Laporan penjualan</a>

                        <p class="sidebar-label mt-3">Pengaturan</p>
                        <a class="sidebar-link {{ request()->routeIs('seller.store.*') ? 'active' : '' }}" href="{{ route('seller.store.edit') }}"><i class="bi bi-shop-window"></i> Profil toko</a>
                        @if (auth()->user()->store)
                            <a class="sidebar-link" href="{{ route('stores.show', auth()->user()->store) }}" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Lihat toko</a>
                        @endif
                    @else
                        <a class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-grid-1x2"></i> Ringkasan</a>
                        <a class="sidebar-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}"><i class="bi bi-receipt"></i> Pesanan</a>
                        <a class="sidebar-link {{ request()->routeIs('admin.stores.*') ? 'active' : '' }}" href="{{ route('admin.stores.index') }}"><i class="bi bi-shop"></i> Toko</a>
                        <a class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="bi bi-people"></i> Pengguna</a>
                        <a class="sidebar-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}"><i class="bi bi-tags"></i> Kategori</a>
                        <a class="sidebar-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}"><i class="bi bi-bar-chart-line"></i> Laporan penjualan</a>
                    @endif
                </nav>

                <div class="sidebar-foot">
                    <a href="{{ route('home') }}"><i class="bi bi-arrow-left"></i> Kembali ke situs</a>
                    <div class="sidebar-user">
                        <span class="avatar-xs">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span>
                        <div>
                            <strong>{{ auth()->user()->name }}</strong>
                            <span>{{ auth()->user()->role->label() }}</span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="ms-auto">
                            @csrf
                            <button type="submit" class="btn-logout" aria-label="Keluar"><i class="bi bi-box-arrow-right"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <div class="dashboard-main">
            <header class="dashboard-topbar">
                <button class="btn btn-topbar d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardSidebar" aria-controls="dashboardSidebar">
                    <i class="bi bi-list"></i>
                </button>
                <div class="topbar-title">
                    <p>@yield('eyebrow', $isSeller ? 'Dashboard Penjual' : 'Dashboard Admin')</p>
                    <h1>@yield('page_title', 'Ringkasan')</h1>
                </div>
                <div class="topbar-actions">
                    @if (! $isSeller)
                        <span class="topbar-date"><i class="bi bi-calendar3"></i> {{ tanggal_indonesia(now()) }}</span>
                    @endif
                    @if ($isSeller && auth()->user()->store)
                        <a href="{{ route('stores.show', auth()->user()->store) }}" target="_blank" class="btn btn-outline-brand btn-sm d-none d-sm-inline-flex"><i class="bi bi-eye"></i> Lihat toko</a>
                    @endif
                    <a href="{{ route('home') }}" class="icon-btn icon-btn-light" aria-label="Beranda"><i class="bi bi-house"></i></a>
                </div>
            </header>

            <div class="dashboard-content">
                @include('partials.flash')
                @yield('content')
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
