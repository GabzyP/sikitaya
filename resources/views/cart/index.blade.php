@extends('layouts.app')

@section('title', 'Keranjang Belanja')

@section('content')
    <section class="page-banner page-banner-light">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Keranjang</li>
                </ol>
            </nav>
            <h1>Keranjang belanja</h1>
            <p>Periksa produk pilihan Anda sebelum melanjutkan ke pembayaran.</p>
        </div>
    </section>

    <section class="page-section">
        <div class="container">
            @if ($items->isEmpty())
                <div class="empty-state">
                    <span class="empty-icon"><i class="bi bi-bag"></i></span>
                    <h2>Keranjang Anda masih kosong</h2>
                    <p>Mulai jelajahi produk UMKM Kota Medan dan tambahkan produk favorit Anda ke keranjang.</p>
                    <a href="{{ route('products.index') }}" class="btn btn-brand">Mulai belanja <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
            @else
                <div class="cart-layout">
                    <div>
                        <div class="cart-panel">
                            <div class="cart-panel-head">
                                <h2><i class="bi bi-bag me-1 text-brand"></i> Produk pilihan Anda</h2>
                                <span class="text-muted" style="font-size:.72rem;">{{ number_format($items->sum('quantity'), 0, ',', '.') }} item dari {{ $groups->count() }} toko</span>
                            </div>

                            @foreach ($groups as $storeItems)
                                @php($store = $storeItems->first()->product->store)
                                <div class="store-group">
                                    <div class="store-group-head">
                                        <i class="bi bi-shop"></i>
                                        <a href="{{ route('stores.show', $store) }}" class="text-reset">{{ $store->name }}</a>
                                        <span class="badge-soft ms-auto">{{ $storeItems->count() }} produk</span>
                                    </div>

                                    @foreach ($storeItems as $item)
                                        @php($product = $item->product)
                                        <div class="cart-item">
                                            <a href="{{ route('products.show', $product) }}" class="cart-thumb">
                                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                                            </a>
                                            <div class="cart-item-info">
                                                <h3><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>
                                                <span class="cart-item-price">{{ rupiah($product->price) }} · {{ berat($product->weight) }} / item</span>
                                                @if ($product->stock < $item->quantity)
                                                    <div class="text-danger mt-1" style="font-size:.66rem;">Stok tersisa {{ $product->stock }} item.</div>
                                                @endif
                                            </div>
                                            <div class="cart-item-actions">
                                                <form action="{{ route('cart.update', $item) }}" method="POST" class="d-flex align-items-center gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div class="qty-control">
                                                        <button type="button" data-qty-minus aria-label="Kurangi jumlah"><i class="bi bi-dash"></i></button>
                                                        <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="{{ max(1, $product->stock) }}" aria-label="Jumlah {{ $product->name }}">
                                                        <button type="button" data-qty-plus aria-label="Tambah jumlah"><i class="bi bi-plus"></i></button>
                                                    </div>
                                                    <button type="submit" class="btn btn-outline-brand btn-sm" title="Perbarui jumlah">Perbarui</button>
                                                </form>
                                                <span class="cart-item-subtotal">{{ rupiah($product->price * $item->quantity) }}</span>
                                                <form action="{{ route('cart.destroy', $item) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-remove" aria-label="Hapus {{ $product->name }}" title="Hapus produk"><i class="bi bi-trash3"></i></button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                            <a href="{{ route('products.index') }}" class="btn btn-ghost btn-sm"><i class="bi bi-arrow-left me-1"></i> Lanjut belanja</a>
                            <form action="{{ route('cart.clear') }}" method="POST" onsubmit="return confirm('Kosongkan seluruh keranjang belanja?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger-soft btn-sm"><i class="bi bi-trash3 me-1"></i> Kosongkan keranjang</button>
                            </form>
                        </div>
                    </div>

                    <aside class="cart-summary">
                        <h2>Ringkasan belanja</h2>
                        <div class="summary-row">
                            <span>Jumlah item</span>
                            <strong>{{ number_format($items->sum('quantity'), 0, ',', '.') }} item</strong>
                        </div>
                        <div class="summary-row">
                            <span>Total berat</span>
                            <strong>{{ berat($totalWeight) }}</strong>
                        </div>
                        <div class="summary-row">
                            <span>Subtotal produk</span>
                            <strong>{{ rupiah($subtotal) }}</strong>
                        </div>
                        <div class="summary-row">
                            <span>Ongkos kirim</span>
                            <strong class="text-muted">Dihitung saat checkout</strong>
                        </div>
                        <div class="summary-row total">
                            <span>Perkiraan total</span>
                            <strong>{{ rupiah($subtotal) }}</strong>
                        </div>

                        <div class="info-note">
                            <i class="bi bi-info-circle"></i>
                            <span>Produk dari toko berbeda akan dibuat menjadi pesanan terpisah, masing-masing dengan ongkir dan pembayaran sendiri.</span>
                        </div>

                        <a href="{{ route('checkout.index') }}" class="btn btn-brand w-100 mt-3">
                            Lanjut ke checkout <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                        <p class="form-note mb-0 text-center">Anda perlu masuk sebagai pembeli untuk menyelesaikan pesanan.</p>
                    </aside>
                </div>
            @endif
        </div>
    </section>
@endsection
