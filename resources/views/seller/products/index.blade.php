@extends('layouts.dashboard')

@section('title', 'Produk Toko')
@section('eyebrow', 'Katalog Toko')
@section('page_title', 'Produk')

@section('content')
    <div class="page-head">
        <div>
            <h1>Produk {{ $store->name }}</h1>
            <p>Kelola katalog, harga, stok, dan status tampil produk Anda.</p>
        </div>
        <div class="actions">
            <a href="{{ route('stores.show', $store) }}" target="_blank" class="btn btn-outline-brand btn-sm btn-dash"><i class="bi bi-box-arrow-up-right me-1"></i> Lihat toko</a>
            <a href="{{ route('seller.products.create') }}" class="btn btn-brand btn-sm btn-dash"><i class="bi bi-plus-lg me-1"></i> Tambah produk</a>
        </div>
    </div>

    <form action="{{ route('seller.products.index') }}" method="GET" class="filter-bar">
        <div class="nav-search" style="min-width:260px;">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama produk..." aria-label="Cari produk">
            <button type="submit" class="visually-hidden">Cari</button>
        </div>
        <select name="status" class="form-select" style="max-width:190px;" aria-label="Filter status produk">
            <option value="">Semua status</option>
            <option value="active" @selected(($filters['status'] ?? null) === 'active')>Aktif</option>
            <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Nonaktif</option>
            <option value="low" @selected(($filters['status'] ?? null) === 'low')>Stok rendah (≤ 5)</option>
        </select>
        <button type="submit" class="btn btn-brand">Terapkan</button>
        @if (filled($filters['q'] ?? null) || filled($filters['status'] ?? null))
            <a href="{{ route('seller.products.index') }}" class="btn btn-ghost">Reset</a>
        @endif
        <span class="ms-auto text-muted" style="font-size:.68rem;">{{ $products->total() }} produk ditemukan</span>
    </form>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-clean">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="product-thumb">
                                    <div>
                                        <div class="cell-main">{{ $product->name }}</div>
                                        <div class="cell-sub">{{ $product->slug }} · {{ berat($product->weight) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $product->category?->name ?? '-' }}</td>
                            <td class="cell-money">{{ rupiah($product->price) }}</td>
                            <td style="min-width:110px;">
                                <div class="d-flex justify-content-between align-items-center" style="font-size:.7rem;">
                                    <span class="fw-semibold">{{ $product->stock }}</span>
                                    @if ($product->is_low_stock)
                                        <span class="badge-soft amber">Rendah</span>
                                    @endif
                                </div>
                                <div class="progress-stock {{ $product->stock <= 0 ? 'empty' : ($product->is_low_stock ? 'low' : '') }}">
                                    <span style="width: {{ $product->stock <= 0 ? 0 : min(100, ($product->stock / 50) * 100) }}%;"></span>
                                </div>
                            </td>
                            <td>
                                @if ($product->is_active)
                                    <span class="badge-soft green"><i class="bi bi-check-circle"></i> Aktif</span>
                                @else
                                    <span class="badge-soft"><i class="bi bi-pause-circle"></i> Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('products.show', $product) }}" target="_blank" class="btn-table" title="Lihat di toko" aria-label="Lihat {{ $product->name }}"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('seller.products.edit', $product) }}" class="btn-table" title="Ubah" aria-label="Ubah {{ $product->name }}"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('seller.products.toggle', $product) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn-table" title="{{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" aria-label="{{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $product->name }}">
                                            <i class="bi {{ $product->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('seller.products.destroy', $product) }}" method="POST" onsubmit="return confirm('Hapus produk {{ addslashes($product->name) }}? Tindakan ini tidak dapat dibatalkan.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-table danger" title="Hapus" aria-label="Hapus {{ $product->name }}"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="empty-state border-0 p-0">
                                    <span class="empty-icon"><i class="bi bi-box-seam"></i></span>
                                    <h2>Belum ada produk</h2>
                                    <p>Tambahkan produk pertama Anda agar pembeli dapat menemukannya di katalog Sikitaya.</p>
                                    <a href="{{ route('seller.products.create') }}" class="btn btn-brand btn-sm">Tambah produk</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($products->hasPages())
            <div class="table-footer">
                <span class="page-info">Menampilkan {{ $products->firstItem() }}–{{ $products->lastItem() }} dari {{ $products->total() }} produk</span>
                {{ $products->links() }}
            </div>
        @endif
    </div>
@endsection
