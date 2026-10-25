@extends('layouts.dashboard')

@section('title', 'Dashboard Admin')
@section('eyebrow', 'Dashboard Admin')
@section('page_title', 'Ringkasan platform')

@section('content')
    @php
        $maxRevenue = max(1, (int) $chart->max('revenue'));
    @endphp

    <div class="page-head">
        <div>
            <h1>Ringkasan Sikitaya</h1>
            <p>Pantau aktivitas marketplace UMKM Kota Medan secara menyeluruh.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.reports.index') }}" class="btn btn-brand btn-sm btn-dash"><i class="bi bi-bar-chart-line me-1"></i> Laporan platform</a>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-brand btn-sm btn-dash"><i class="bi bi-receipt me-1"></i> Semua pesanan</a>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label mb-0">Omzet platform</span>
                <span class="kpi-icon"><i class="bi bi-cash-stack"></i></span>
            </div>
            <div class="kpi-value">{{ rupiah($stats['omzet']) }}</div>
            <div class="kpi-note">Pesanan dibayar, dikirim, dan selesai.</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label mb-0">Total pesanan</span>
                <span class="kpi-icon amber"><i class="bi bi-receipt"></i></span>
            </div>
            <div class="kpi-value">{{ number_format($stats['orders'], 0, ',', '.') }}</div>
            <div class="kpi-note"><strong>{{ number_format($stats['pending'], 0, ',', '.') }}</strong> pesanan menunggu tindakan.</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label mb-0">Toko aktif</span>
                <span class="kpi-icon blue"><i class="bi bi-shop"></i></span>
            </div>
            <div class="kpi-value">{{ number_format($stats['active_stores'], 0, ',', '.') }}</div>
            <div class="kpi-note">Dari {{ number_format($stats['stores'], 0, ',', '.') }} toko terdaftar.</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label mb-0">Produk terdaftar</span>
                <span class="kpi-icon purple"><i class="bi bi-box-seam"></i></span>
            </div>
            <div class="kpi-value">{{ number_format($stats['products'], 0, ',', '.') }}</div>
            <div class="kpi-note">{{ number_format($stats['sellers'], 0, ',', '.') }} penjual · {{ number_format($stats['buyers'], 0, ',', '.') }} pembeli.</div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div>
            <div class="panel">
                <div class="panel-head">
                    <h2><i class="bi bi-bar-chart-line"></i> Omzet platform 6 bulan</h2>
                    <a href="{{ route('admin.reports.index') }}">Lihat laporan <i class="bi bi-arrow-right"></i></a>
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
                    <a href="{{ route('admin.orders.index') }}">Semua pesanan <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table table-clean">
                        <thead>
                            <tr>
                                <th>Pesanan</th>
                                <th>Toko</th>
                                <th>Pembeli</th>
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
                                    <td>{{ $order->store->name }}</td>
                                    <td>{{ $order->user->name }}</td>
                                    <td class="cell-money">{{ rupiah($order->total) }}</td>
                                    <td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="{{ route('admin.orders.show', $order) }}" class="btn-table" aria-label="Lihat pesanan {{ $order->order_number }}"><i class="bi bi-eye"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted" style="font-size:.78rem;">Belum ada pesanan di platform.</td>
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
                    <h2><i class="bi bi-trophy"></i> Toko dengan omzet tertinggi</h2>
                </div>
                <div class="panel-body py-2">
                    @forelse ($topStores as $topStore)
                        <div class="stat-mini">
                            <span class="text-truncate" style="max-width:60%;">
                                <i class="bi bi-shop"></i> {{ $topStore->store?->name ?? 'Toko dihapus' }}
                            </span>
                            <strong>{{ rupiah($topStore->revenue) }}</strong>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0 py-3" style="font-size:.75rem;">Belum ada omzet terverifikasi.</p>
                    @endforelse
                </div>
            </div>

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
                    <h2><i class="bi bi-people"></i> Pengguna platform</h2>
                </div>
                <div class="panel-body py-2">
                    <div class="stat-mini">
                        <span><i class="bi bi-shop"></i> Penjual</span>
                        <strong>{{ number_format($stats['sellers'], 0, ',', '.') }}</strong>
                    </div>
                    <div class="stat-mini">
                        <span><i class="bi bi-bag"></i> Pembeli</span>
                        <strong>{{ number_format($stats['buyers'], 0, ',', '.') }}</strong>
                    </div>
                    <div class="stat-mini">
                        <span><i class="bi bi-shop-window"></i> Toko nonaktif</span>
                        <strong>{{ number_format($stats['stores'] - $stats['active_stores'], 0, ',', '.') }}</strong>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2><i class="bi bi-lightning-charge"></i> Aksi cepat</h2>
                </div>
                <div class="panel-body d-grid gap-2">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-brand btn-sm text-start"><i class="bi bi-people me-1"></i> Kelola pengguna</a>
                    <a href="{{ route('admin.stores.index') }}" class="btn btn-outline-brand btn-sm text-start"><i class="bi bi-shop me-1"></i> Kelola toko</a>
                    <a href="{{ route('admin.categories.create') }}" class="btn btn-outline-brand btn-sm text-start"><i class="bi bi-plus-circle me-1"></i> Tambah kategori</a>
                    <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-brand btn-sm text-start"><i class="bi bi-download me-1"></i> Unduh laporan platform</a>
                </div>
            </div>
        </div>
    </div>
@endsection
