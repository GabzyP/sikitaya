@php
    $storeDescription = $store->description ?: 'Toko UMKM Kota Medan yang dikelola langsung oleh pemiliknya.';
@endphp

<article class="store-card">
    <div class="store-mark">
        @if ($store->logo_url)
            <img src="{{ $store->logo_url }}" alt="Foto {{ $store->name }}">
        @else
            {{ $store->initials }}
        @endif
    </div>
    <div class="store-info">
        <h3><a href="{{ route('stores.show', $store) }}">{{ $store->name }}</a></h3>
        <p class="store-location"><i class="bi bi-geo-alt"></i> {{ $store->city_name }}, {{ $store->province_name }}</p>
        <p class="store-desc">{{ $storeDescription }}</p>
        <div class="store-counts">
            <span><i class="bi bi-box-seam"></i> {{ $store->products_count }} produk</span>
            <span><i class="bi bi-patch-check"></i> Toko aktif</span>
        </div>
        <a href="{{ route('stores.show', $store) }}" class="btn btn-outline-brand btn-sm mt-3">Kunjungi toko <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
</article>
