@extends('layouts.dashboard')

@section('title', 'Toko')
@section('eyebrow', 'Manajemen')
@section('page_title', 'Toko UMKM')

@section('content')
    <div class="page-head">
        <div>
            <h1>Daftar toko</h1>
            <p>Kelola toko UMKM yang berjualan di platform Sikitaya.</p>
        </div>
    </div>

    <form action="{{ route('admin.stores.index') }}" method="GET" class="filter-bar">
        <div class="nav-search" style="min-width:260px;">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama toko atau pemilik..." aria-label="Cari toko">
            <button type="submit" class="visually-hidden">Cari</button>
        </div>
        <select name="status" class="form-select" style="max-width:160px;" aria-label="Filter status toko">
            <option value="">Semua status</option>
            <option value="active" @selected(($filters['status'] ?? null) === 'active')>Aktif</option>
            <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Nonaktif</option>
        </select>
        <button type="submit" class="btn btn-brand">Terapkan</button>
        @if (filled($filters['q'] ?? null) || filled($filters['status'] ?? null))
            <a href="{{ route('admin.stores.index') }}" class="btn btn-ghost">Reset</a>
        @endif
        <span class="ms-auto text-muted" style="font-size:.68rem;">{{ $stores->total() }} toko</span>
    </form>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-clean">
                <thead>
                    <tr>
                        <th>Toko</th>
                        <th>Pemilik</th>
                        <th>Lokasi</th>
                        <th class="text-center">Produk</th>
                        <th class="text-center">Pesanan</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stores as $store)
                        <tr>
                            <td>
                                <div class="store-cell">
                                    <span class="avatar-store">
                                        @if ($store->logo_url)
                                            <img src="{{ $store->logo_url }}" alt="{{ $store->name }}">
                                        @else
                                            {{ $store->initials }}
                                        @endif
                                    </span>
                                    <div>
                                        <a href="{{ route('stores.show', $store) }}" target="_blank" class="cell-main text-decoration-none">{{ $store->name }}</a>
                                        <div class="cell-sub">{{ $store->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="cell-main">{{ $store->owner?->name ?? '-' }}</div>
                                <div class="cell-sub">{{ $store->owner?->email ?? '' }}</div>
                            </td>
                            <td>
                                <div>{{ $store->city_name }}</div>
                                <div class="cell-sub">{{ $store->province_name }}</div>
                            </td>
                            <td class="text-center">{{ $store->products_count }}</td>
                            <td class="text-center">{{ $store->orders_count }}</td>
                            <td>
                                @if ($store->is_active)
                                    <span class="badge-soft green"><i class="bi bi-check-circle"></i> Aktif</span>
                                @else
                                    <span class="badge-soft red"><i class="bi bi-x-circle"></i> Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('stores.show', $store) }}" target="_blank" class="btn-table" title="Lihat toko" aria-label="Lihat toko {{ $store->name }}"><i class="bi bi-box-arrow-up-right"></i></a>
                                    <form action="{{ route('admin.stores.toggle', $store) }}" method="POST" onsubmit="return confirm('{{ $store->is_active ? 'Nonaktifkan' : 'Aktifkan' }} toko {{ addslashes($store->name) }}?')">
                                        @csrf
                                        <button type="submit" class="btn-table {{ $store->is_active ? 'danger' : '' }}" title="{{ $store->is_active ? 'Nonaktifkan toko' : 'Aktifkan toko' }}" aria-label="{{ $store->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $store->name }}">
                                            <i class="bi {{ $store->is_active ? 'bi-shop-window' : 'bi-check2-circle' }}"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state border-0 p-0">
                                    <span class="empty-icon"><i class="bi bi-shop"></i></span>
                                    <h2>Tidak ada toko</h2>
                                    <p>Tidak ada toko yang cocok dengan filter Anda.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($stores->hasPages())
            <div class="table-footer">
                <span class="page-info">Menampilkan {{ $stores->firstItem() }}–{{ $stores->lastItem() }} dari {{ $stores->total() }} toko</span>
                {{ $stores->links() }}
            </div>
        @endif
    </div>
@endsection
