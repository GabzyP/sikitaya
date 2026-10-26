@php
    $gateTitle = $title ?? 'Masuk untuk melihat lebih jauh';
    $gateMessage = $message ?? 'Buat akun atau masuk untuk membuka seluruh katalog, melihat detail toko, dan mulai berbelanja dari UMKM Kota Medan.';
@endphp

<div class="guest-gate">
    <span class="guest-gate-icon"><i class="bi bi-lock"></i></span>
    <div class="guest-gate-copy">
        <h2>{{ $gateTitle }}</h2>
        <p>{{ $gateMessage }}</p>
    </div>
    <div class="guest-gate-actions">
        <a href="{{ route('login') }}" class="btn btn-brand">Masuk</a>
        <a href="{{ route('register') }}" class="btn btn-outline-brand">Daftar</a>
    </div>
</div>
