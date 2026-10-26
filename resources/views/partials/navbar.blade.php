@php
    $cartCount = auth()->check() ? auth()->user()->cartItems()->sum('quantity') : 0;
    $isDashboard = request()->routeIs('seller.*') || request()->routeIs('admin.*');
@endphp

<header class="site-header">
    <nav class="navbar navbar-expand-lg main-nav">
        <div class="container">
            <a class="navbar-brand brand" href="{{ route('home') }}">
                @include('partials.brand-logo')
                <span class="brand-text">
                    <span class="brand-name">Sikitaya</span>
                    <span class="brand-tag">UMKM KOTA MEDAN</span>
                </span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Buka navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav nav-links mx-lg-auto">
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">Produk</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('stores.*') ? 'active' : '' }}" href="{{ route('stores.index') }}">Toko</a></li>
                </ul>

                <form class="nav-search" action="{{ route('products.index') }}" method="GET" role="search">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" value="{{ request()->routeIs('products.index') ? request('q') : '' }}" placeholder="Cari produk atau toko" aria-label="Cari produk atau toko">
                    <button type="submit" class="visually-hidden">Cari</button>
                </form>

                <div class="nav-actions">
                    @auth
                        <div class="dropdown">
                            <button class="account-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="avatar-xs">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span>
                                <span class="d-none d-xl-inline">{{ Str::of(auth()->user()->name)->explode(' ')->first() }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end account-menu">
                                <li class="px-3 py-2">
                                    <div class="fw-semibold">{{ auth()->user()->name }}</div>
                                    <div class="small text-muted">{{ auth()->user()->email }}</div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                @if (auth()->user()->isBuyer())
                                    <li><a class="dropdown-item" href="{{ route('orders.index') }}"><i class="bi bi-receipt"></i> Pesanan saya</a></li>
                                    <li><a class="dropdown-item" href="{{ route('cart.index') }}"><i class="bi bi-bag"></i> Keranjang @if ($cartCount > 0)<span class="text-muted">({{ $cartCount > 99 ? '99+' : $cartCount }})</span>@endif</a></li>
                                @else
                                    <li><a class="dropdown-item" href="{{ auth()->user()->dashboardRoute() }}"><i class="bi bi-speedometer2"></i> Dashboard {{ auth()->user()->role->label() }}</a></li>
                                @endif
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right"></i> Keluar</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Masuk</a>
                        <a href="{{ route('register') }}" class="btn btn-brand btn-sm">Daftar</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
</header>
