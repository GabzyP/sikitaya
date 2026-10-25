@extends('layouts.dashboard')

@section('title', 'Dashboard Penjual')
@section('eyebrow', 'Dashboard Penjual')
@section('page_title', 'Ringkasan toko')

@section('content')
    @php
        $maxRevenue = max(1, (int) $chart->max('revenue'));
    @endphp

    <div class="page-head">
        <div>
            <h1>Halo, {{ Str::of(auth()->user()->name)->explode(' ')->first() }} 👋</h1>
            <p>Berikut ringkasan performa {{ $store->name }} bulan ini.</p>
        </div>
        <div class="actions">
            <a href="{{ route('seller.products.create') }}" class="btn btn-brand btn-sm btn-dash"><i class="bi bi-plus-lg me-1"></i> Tambah produk</a>
            <a href="{{ route('seller.orders.index') }}" class="btn btn-outline-brand btn-sm btn-dash"><i class="bi bi-receipt me-1"></i> Kelola pesanan</a>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label mb-0">Omzet bulan ini</span>
                <span class="kpi-icon"><i class="bi bi-cash-coin"></i></span>
            </div>
            <div class="kpi-value">{{ rupiah($stats['omzet_bulan_ini']) }}</div>
            <div class="kpi-note">Dari pesanan berstatus dibayar, dikirim, dan selesai.</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label mb-0">Pesanan bulan ini</span>
                <span class="kpi-icon amber"><i class="bi bi-receipt"></i></span>
            </div>
            <div class="kpi-value">{{ number_format($stats['pesanan_bulan_ini'], 0, ',', '.') }}</div>
            <div class="kpi-note">Seluruh pesanan yang masuk sejak awal bulan.</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label mb-0">Perlu diproses</span>
                <span class="kpi-icon blue"><i class="bi bi-hourglass-split"></i></span>
            </div>
            <div class="kpi-value">{{ number_format($stats['perlu_diproses'], 0, ',', '.') }}</div>
            <div class="kpi-note">
                @if ($stats['menunggu_verifikasi'] > 0)
                    <strong>{{ $stats['menunggu_verifikasi'] }} pembayaran</strong> menunggu verifikasi.
                @else
                    Tidak ada pembayaran menunggu verifikasi.
                @endif
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label mb-0">Produk aktif</span>
                <span class="kpi-icon purple"><i class="bi bi-box-seam"></i></span>
            </div>
            <div class="kpi-value">{{ number_format($stats['produk_aktif'], 0, ',', '.') }}</div>
            <div class="kpi-note">
                @if ($stats['stok_rendah'] > 0)
                    <strong>{{ $stats['stok_rendah'] }} produk</strong> dengan stok rendah.
                @else
                    Semua stok produk dalam kondisi aman.
                @endif
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div>
            <div class="panel">
                <div class="panel-head">
                    <h2><i class="bi bi-bar-chart-line"></i> Tren omzet 6 bulan</h2>
                    <a href="{{ route('seller.reports.index') }}">Lihat laporan <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="panel-body">
                    <div class="chart-bars">
                        @foreach ($chart as $point)
                            @php
                                $height = max(4, round(($point['revenue'] / $maxRevenue) * 100));
                                $label = $point['revenue'] >= 1000000
                                    ? 'Rp '.number_format($point['revenue'] / 1000000, 1, ',', '.').' jt'
                                    : rupiah($point['revenue']);
                            @endphp
                            <div class="chart-bar-col">
                                <div class="chart-bar" style="height: {{ $height }}%;" title="{{ $label }} pada {{ $point['label'] }}">
                                    <span>{{ $label }}</span>
                                </div>
                                <span class="chart-bar-label">{{ $point['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="chart-legend">
                        <span><i style="background:var(--brand);"></i> Omzet terverifikasi</span>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2><i class="bi bi-receipt"></i> Pesanan terbaru</h2>
                    <a href="{{ route('seller.orders.index') }}">Semua pesanan <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table table-clean">
                        <thead>
                            <tr>
                                <th>Pesanan</th>
                                <th>Pembeli</th>
                                <th>Item</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentOrders as $order)
                                <tr>
                                    <td>
                                        <div class="cell-main">{{ $order->order_number }}</div>
                                        <div class="cell-sub">{{ tanggal_indonesia($order->created_at, true) }}</div>
                                    </td>
                                    <td>{{ $order->user->name }}</td>
                                    <td>{{ $order->items->sum('quantity') }}</td>
                                    <td class="cell-money">{{ rupiah($order->total) }}</td>
                                    <td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="{{ route('seller.orders.show', $order) }}" class="btn-table" aria-label="Lihat pesanan {{ $order->order_number }}"><i class="bi bi-eye"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted" style="font-size:.78rem;">Belum ada pesanan masuk.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div>
            <div class="panel">
                <div class="panel-head">
                    <h2><i class="bi bi-pie-chart"></i> Status pesanan</h2>
                </div>
                <div class="panel-body py-2">
                    @foreach (\App\Enums\OrderStatus::cases() as $status)
                        <div class="stat-mini">
                            <span><span class="{{ $status->badgeClass() }}">{{ $status->label() }}</span></span>
                            <strong>{{ number_format($statusCounts[$status->value] ?? 0, 0, ',', '.') }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2><i class="bi bi-trophy"></i> Produk terlaris</h2>
                </div>
                <div class="panel-body py-2">
                    @forelse ($topProducts as $product)
                        <div class="stat-mini">
                            <span class="text-truncate" style="max-width:65%;" title="{{ $product->product_name }}">
                                <i class="bi bi-box-seam"></i> {{ $product->product_name }}
                            </span>
                            <strong>{{ $product->total_qty }} pcs</strong>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0 py-3" style="font-size:.75rem;">Belum ada penjualan terverifikasi.</p>
                    @endforelse
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2><i class="bi bi-lightning-charge"></i> Aksi cepat</h2>
                </div>
                <div class="panel-body d-grid gap-2">
                    <a href="{{ route('seller.products.create') }}" class="btn btn-outline-brand btn-sm text-start"><i class="bi bi-plus-circle me-1"></i> Tambah produk baru</a>
                    <a href="{{ route('seller.orders.index', ['status' => 'menunggu']) }}" class="btn btn-outline-brand btn-sm text-start"><i class="bi bi-check2-square me-1"></i> Verifikasi pembayaran</a>
                    <a href="{{ route('seller.reports.index') }}" class="btn btn-outline-brand btn-sm text-start"><i class="bi bi-download me-1"></i> Unduh laporan penjualan</a>
                    <a href="{{ route('seller.store.edit') }}" class="btn btn-outline-brand btn-sm text-start"><i class="bi bi-shop-window me-1"></i> Perbarui profil toko</a>
                </div>
            </div>

            @if ($stats['stok_rendah'] > 0)
                <div class="panel" style="border-color:#f0e7d2;">
                    <div class="panel-body">
                        <div class="d-flex gap-2">
                            <i class="bi bi-exclamation-triangle text-amber fs-5"></i>
                            <div>
                                <strong class="d-block" style="font-size:.8rem;">Stok perlu diisi ulang</strong>
                                <p class="mb-2" style="font-size:.72rem;color:var(--muted);">{{ $stats['stok_rendah'] }} produk aktif memiliki stok 5 atau kurang.</p>
                                <a href="{{ route('seller.products.index', ['status' => 'low']) }}" class="btn btn-amber btn-sm">Lihat produk</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
