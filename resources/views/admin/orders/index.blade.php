@extends('layouts.dashboard')

@section('title', 'Pesanan Platform')
@section('eyebrow', 'Transaksi')
@section('page_title', 'Pesanan')

@section('content')
    <div class="page-head">
        <div>
            <h1>Pesanan platform</h1>
            <p>Pantau seluruh transaksi yang terjadi di Sikitaya.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-brand btn-sm btn-dash"><i class="bi bi-bar-chart-line me-1"></i> Laporan penjualan</a>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="filter-tabs">
            <a class="{{ empty($filters['status'] ?? null) ? 'active' : '' }}" href="{{ route('admin.orders.index', array_filter(['store' => $filters['store'] ?? null, 'q' => $filters['q'] ?? null])) }}">
                Semua <span>{{ $statusCounts->sum() }}</span>
            </a>
            @foreach (\App\Enums\OrderStatus::cases() as $status)
                <a class="{{ ($filters['status'] ?? null) === $status->value ? 'active' : '' }}" href="{{ route('admin.orders.index', array_filter(['status' => $status->value, 'store' => $filters['store'] ?? null, 'q' => $filters['q'] ?? null])) }}">
                    {{ $status->label() }} <span>{{ $statusCounts[$status->value] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <form action="{{ route('admin.orders.index') }}" method="GET" class="filter-bar">
        @if ($filters['status'] ?? null)
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
        @endif
        <div class="nav-search" style="min-width:240px;">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari no. pesanan / penerima / pembeli" aria-label="Cari pesanan">
            <button type="submit" class="visually-hidden">Cari</button>
        </div>
        <select name="store" class="form-select" style="max-width:220px;" aria-label="Filter toko">
            <option value="">Semua toko</option>
            @foreach ($stores as $store)
                <option value="{{ $store->id }}" @selected((string) ($filters['store'] ?? '') === (string) $store->id)>{{ $store->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-brand">Terapkan</button>
        @if (filled($filters['q'] ?? null) || filled($filters['store'] ?? null) || filled($filters['status'] ?? null))
            <a href="{{ route('admin.orders.index') }}" class="btn btn-ghost">Reset</a>
        @endif
        <span class="ms-auto text-muted" style="font-size:.68rem;">{{ $orders->total() }} pesanan</span>
    </form>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-clean">
                <thead>
                    <tr>
                        <th>Pesanan</th>
                        <th>Toko</th>
                        <th>Pembeli</th>
                        <th class="text-center">Item</th>
                        <th>Pembayaran</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                <div class="cell-main">{{ $order->order_number }}</div>
                                <div class="cell-sub">{{ tanggal_indonesia($order->created_at, true) }}</div>
                            </td>
                            <td>
                                <div class="cell-main">{{ $order->store->name }}</div>
                                <div class="cell-sub">{{ $order->store->city_name }}</div>
                            </td>
                            <td>
                                <div>{{ $order->user->name }}</div>
                                <div class="cell-sub">{{ $order->recipient_name }}</div>
                            </td>
                            <td class="text-center">{{ $order->items->sum('quantity') }}</td>
                            <td>
                                <span class="badge-soft"><i class="bi {{ $order->payment_method->icon() }}"></i> {{ $order->payment_method->shortLabel() }}</span>
                            </td>
                            <td class="text-end cell-money">{{ rupiah($order->total) }}</td>
                            <td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="btn-table" title="Lihat detail" aria-label="Lihat pesanan {{ $order->order_number }}"><i class="bi bi-eye"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="empty-state border-0 p-0">
                                    <span class="empty-icon"><i class="bi bi-receipt"></i></span>
                                    <h2>Tidak ada pesanan</h2>
                                    <p>Tidak ada pesanan yang cocok dengan filter Anda.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($orders->hasPages())
            <div class="table-footer">
                <span class="page-info">Menampilkan {{ $orders->firstItem() }}–{{ $orders->lastItem() }} dari {{ $orders->total() }} pesanan</span>
                {{ $orders->links() }}
            </div>
        @endif
    </div>
@endsection
