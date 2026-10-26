@extends('layouts.dashboard')

@section('title', 'Pesanan Toko')
@section('eyebrow', 'Transaksi')
@section('page_title', 'Pesanan')

@section('content')
    <div class="page-head">
        <div>
            <h1>Pesanan masuk</h1>
            <p>Verifikasi pembayaran, input resi, dan pantau pesanan {{ $store->name }}.</p>
        </div>
        <div class="actions">
            <a href="{{ route('seller.reports.index') }}" class="btn btn-outline-brand btn-sm btn-dash"><i class="bi bi-bar-chart-line me-1"></i> Laporan penjualan</a>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="filter-tabs">
            <a class="{{ empty($filters['status'] ?? null) ? 'active' : '' }}" href="{{ route('seller.orders.index', array_filter(['q' => $filters['q'] ?? null])) }}">
                Semua <span>{{ $statusCounts->sum() }}</span>
            </a>
            @foreach (\App\Enums\OrderStatus::cases() as $status)
                <a class="{{ ($filters['status'] ?? null) === $status->value ? 'active' : '' }}" href="{{ route('seller.orders.index', array_filter(['status' => $status->value, 'q' => $filters['q'] ?? null])) }}">
                    {{ $status->label() }} <span>{{ $statusCounts[$status->value] ?? 0 }}</span>
                </a>
            @endforeach
        </div>

        <form action="{{ route('seller.orders.index') }}" method="GET" class="d-flex gap-2 ms-auto">
            @if ($filters['status'] ?? null)
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
            @endif
            <div class="nav-search" style="min-width:220px;">
                <i class="bi bi-search"></i>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari no. pesanan / penerima" aria-label="Cari pesanan">
                <button type="submit" class="visually-hidden">Cari</button>
            </div>
            <button type="submit" class="btn btn-outline-brand btn-sm">Cari</button>
        </form>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-clean">
                <thead>
                    <tr>
                        <th>Pesanan</th>
                        <th>Pembeli</th>
                        <th>Item</th>
                        <th>Total</th>
                        <th>Pembayaran</th>
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
                                <div class="cell-main">{{ $order->recipient_name }}</div>
                                <div class="cell-sub">{{ $order->user->name }}</div>
                            </td>
                            <td>{{ $order->items->sum('quantity') }} item</td>
                            <td class="cell-money">{{ rupiah($order->total) }}</td>
                            <td>
                                <span class="badge-soft"><i class="bi {{ $order->payment_method->icon() }}"></i> {{ $order->payment_method->shortLabel() }}</span>
                                @if ($order->paymentTransaction)
                                    <div class="mt-1"><span class="{{ $order->paymentTransaction->status->badgeClass() }}">{{ $order->paymentTransaction->status->label() }}</span></div>
                                @elseif ($order->status === \App\Enums\OrderStatus::Menunggu && $order->requiresPaymentProof() && ! $order->payment_proof)
                                    <div class="cell-sub text-danger">Belum ada bukti</div>
                                @endif
                            </td>
                            <td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('seller.orders.show', $order) }}" class="btn-table" title="Lihat detail" aria-label="Lihat pesanan {{ $order->order_number }}"><i class="bi bi-eye"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state border-0 p-0">
                                    <span class="empty-icon"><i class="bi bi-receipt"></i></span>
                                    <h2>Belum ada pesanan</h2>
                                    <p>{{ filled($filters['status'] ?? null) || filled($filters['q'] ?? null) ? 'Tidak ada pesanan yang cocok dengan filter Anda.' : 'Pesanan baru dari pembeli akan muncul di sini.' }}</p>
                                    @if (filled($filters['status'] ?? null) || filled($filters['q'] ?? null))
                                        <a href="{{ route('seller.orders.index') }}" class="btn btn-outline-brand btn-sm">Lihat semua pesanan</a>
                                    @endif
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
