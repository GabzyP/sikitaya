<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/hanni.jpg') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Masuk') · Sikitaya</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="auth-shell">
        <section class="auth-panel">
            <a href="{{ route('home') }}" class="brand brand-light text-decoration-none">
                @include('partials.brand-logo')
                <span class="brand-text">
                    <span class="brand-name">Sikitaya</span>
                    <span class="brand-tag">UMKM KOTA MEDAN</span>
                </span>
            </a>
            <div class="auth-panel-body">
                <p class="eyebrow eyebrow-light">Belanja lokal, berdampak nyata</p>
                <h1>Dari tangan warga Medan, untuk meja Anda.</h1>
                <p class="auth-panel-lead">Temukan kuliner, kopi, tenun, dan oleh-oleh pilihan langsung dari pelaku UMKM Kota Medan.</p>
                <div class="auth-collage" aria-hidden="true">
                    <img src="{{ asset('images/products/rendang-dapur-bu-sari.jpg') }}" alt="">
                    <img class="collage-main" src="{{ asset('images/products/kopi-arabika-gayo.jpg') }}" alt="">
                    <img src="{{ asset('images/products/ulos-sadum.jpg') }}" alt="">
                </div>
                <div class="auth-testimonial">
                    <div class="stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
                    <p>“Satu tempat untuk menemukan produk terbaik dari UMKM Medan.”</p>
                    <span>— Komunitas UMKM Medan</span>
                </div>
            </div>
            <p class="auth-panel-foot mb-0">© {{ date('Y') }} Sikitaya · Marketplace UMKM Kota Medan</p>
        </section>

        <section class="auth-form-side">
            <div class="auth-form-wrap">
                <a href="{{ route('home') }}" class="brand auth-mobile-brand text-decoration-none">
                    @include('partials.brand-logo')
                    <span class="brand-text">
                        <span class="brand-name">Sikitaya</span>
                        <span class="brand-tag">UMKM KOTA MEDAN</span>
                    </span>
                </a>
                <a href="{{ route('home') }}" class="auth-back"><i class="bi bi-arrow-left"></i> Kembali ke beranda</a>
                @include('partials.flash')
                @yield('content')
            </div>
        </section>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
