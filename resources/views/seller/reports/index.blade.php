@extends('layouts.dashboard')

@section('title', 'Laporan Penjualan')
@section('eyebrow', 'Analitik')
@section('page_title', 'Laporan penjualan')

@section('content')
    <div class="page-head">
        <div>
            <h1>Laporan penjualan</h1>
            <p>Unduh rekap transaksi {{ $store->name }} dalam format Excel atau PDF.</p>
        </div>
    </div>

    <form action="{{ route('seller.reports.index') }}" method="GET" class="report-filters">
        <div class="row g-2 align-items-end">
            <div class="col-sm-6 col-lg-3">
                <label for="from" class="form-label">Dari tanggal</label>
                <input type="date" id="from" name="from" value="{{ $filters['from']->format('Y-m-d') }}" class="form-control" required>
            </div>
            <div class="col-sm-6 col-lg-3">
                <label for="to" class="form-label">Sampai tanggal</label>
                <input type="date" id="to" name="to" value="{{ $filters['to']->format('Y-m-d') }}" class="form-control" required>
            </div>
            <div class="col-sm-6 col-lg-3">
                <label for="status" class="form-label">Status pesanan</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua status</option>
                    @foreach (\App\Enums\OrderStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-brand w-100"><i class="bi bi-funnel me-1"></i> Terapkan</button>
                <a href="{{ route('seller.reports.index') }}" class="btn btn-ghost" title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </div>
    </form>

    <div class="report-summary">
        <div class="report-kpi">
            <span>Total pesanan</span>
            <strong>{{ number_format($summary['orders'], 0, ',', '.') }}</strong>
        </div>
        <div class="report-kpi">
            <span>Item terjual</span>
            <strong>{{ number_format($summary['items'], 0, ',', '.') }}</strong>
        </div>
        <div class="report-kpi">
            <span>Omzet terverifikasi</span>
            <strong>{{ rupiah($summary['omzet']) }}</strong>
        </div>
        <div class="report-kpi">
            <span>Menunggu</span>
            <strong>{{ number_format($summary['pending'], 0, ',', '.') }}</strong>
        </div>
    </div>

    <div class="report-head">
        <div>
            <h2>Rincian transaksi</h2>
            <span class="meta">
                Periode {{ tanggal_indonesia($filters['from']) }} – {{ tanggal_indonesia($filters['to']) }}
                @if ($filters['status'])
                    · status {{ $filters['status']->label() }}
                @else
                    · semua status
                @endif
            </span>
        </div>
        <div class="export-actions">
            <a href="{{ route('seller.reports.excel', request()->only(['from', 'to', 'status'])) }}" class="btn-export excel"><i class="bi bi-file-earmark-excel"></i> Unduh Excel</a>
            <a href="{{ route('seller.reports.pdf', request()->only(['from', 'to', 'status'])) }}" class="btn-export pdf"><i class="bi bi-file-earmark-pdf"></i> Unduh PDF</a>
        </div>
    </div>

    <div class="info-banner">
        <i class="bi bi-info-circle"></i>
        <span>Omzet dihitung dari pesanan berstatus <strong>dibayar</strong>, <strong>dikirim</strong>, dan <strong>selesai</strong>. Pesanan yang masih menunggu tidak termasuk omzet.</span>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-clean">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>No. pesanan</th>
                        <th>Pembeli</th>
                        <th>Status</th>
                        <th class="text-center">Item</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Ongkir</th>
                        <th class="text-end">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                <div class="cell-main">{{ $order->created_at->format('d/m/Y') }}</div>
                                <div class="cell-sub">{{ $order->created_at->format('H:i') }} WIB</div>
                            </td>
                            <td class="cell-main">{{ $order->order_number }}</td>
                            <td>
                                <div>{{ $order->user->name }}</div>
                                <div class="cell-sub">{{ $order->payment_method->shortLabel() }}</div>
                            </td>
                            <td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                            <td class="text-center">{{ $order->items->sum('quantity') }}</td>
                            <td class="text-end">{{ rupiah($order->subtotal) }}</td>
                            <td class="text-end">{{ rupiah($order->shipping_cost) }}</td>
                            <td class="text-end cell-money">{{ rupiah($order->total) }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('seller.orders.show', $order) }}" class="btn-table" title="Lihat pesanan" aria-label="Lihat pesanan {{ $order->order_number }}"><i class="bi bi-eye"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="empty-state border-0 p-0">
                                    <span class="empty-icon"><i class="bi bi-bar-chart-line"></i></span>
                                    <h2>Belum ada transaksi</h2>
                                    <p>Tidak ada pesanan pada periode dan filter yang dipilih.</p>
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
