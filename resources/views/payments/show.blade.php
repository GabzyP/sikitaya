@extends('layouts.app')

@section('title', 'Pembayaran '.$paymentTransaction->reference)

@php
    $latestAttempt = $paymentTransaction->attempts->first();
    $isPaid = $paymentTransaction->status === \App\Enums\PaymentTransactionStatus::Paid;
    $isPending = $paymentTransaction->status === \App\Enums\PaymentTransactionStatus::Pending;
    $canRetry = $paymentTransaction->status->canRetry() && ! $paymentTransaction->orders->contains(fn ($order) => $order->status === \App\Enums\OrderStatus::Dibayar);
@endphp

@section('content')
    <section class="page-banner page-banner-light">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">Pesanan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pembayaran</li>
                </ol>
            </nav>
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <h1 class="mb-1">Pembayaran online</h1>
                    <p class="mb-0">{{ $paymentTransaction->reference }} · dibuat {{ tanggal_indonesia($paymentTransaction->created_at, true) }}</p>
                </div>
                <span class="{{ $paymentTransaction->status->badgeClass() }}">{{ $paymentTransaction->status->label() }}</span>
            </div>
        </div>
    </section>

    <section class="page-section">
        <div class="container">
            <div class="detail-grid">
                <div>
                    <div class="detail-panel">
                        <h2><i class="bi bi-credit-card-2-front"></i> Ringkasan transaksi gabungan</h2>
                        <div class="detail-list">
                            <div><span class="label">Total tagihan</span><span class="value text-brand fs-5">{{ rupiah($paymentTransaction->gross_amount) }}</span></div>
                            <div><span class="label">Metode alokasi</span><span class="value">{{ $paymentTransaction->allocation_method?->label() ?? '-' }}</span></div>
                            <div><span class="label">Midtrans order ID</span><span class="value">{{ $paymentTransaction->midtrans_order_id ?: '-' }}</span></div>
                            <div><span class="label">Batas pembayaran</span><span class="value">{{ $paymentTransaction->expires_at ? tanggal_indonesia($paymentTransaction->expires_at, true) : '-' }}</span></div>
                            <div><span class="label">Dibayar pada</span><span class="value">{{ $paymentTransaction->paid_at ? tanggal_indonesia($paymentTransaction->paid_at, true) : '-' }}</span></div>
                        </div>

                        <div class="info-note mt-3">
                            <i class="bi bi-shield-check"></i>
                            <span>Dana penjual dihitung dari total pesanan masing-masing toko. Komisi platform saat ini <strong>{{ rupiah($paymentTransaction->platform_commission) }}</strong>. Biaya Midtrans ditanggung platform dan tidak mengurangi alokasi penjual.</span>
                        </div>

                        @if ($paymentTransaction->failure_reason)
                            <div class="alert alert-warning py-2 px-3 mt-3 mb-0" style="font-size:.74rem;">
                                <i class="bi bi-exclamation-triangle me-1"></i> {{ $paymentTransaction->failure_reason }}
                            </div>
                        @endif

                        @if ($latestAttempt?->error)
                            <div class="alert alert-danger py-2 px-3 mt-3 mb-0" style="font-size:.74rem;">
                                <i class="bi bi-x-octagon me-1"></i> Percobaan terakhir: {{ $latestAttempt->error }}
                            </div>
                        @endif
                    </div>

                    <div class="detail-panel">
                        <h2><i class="bi bi-bag-check"></i> Pesanan dalam transaksi ini</h2>
                        <div class="table-responsive">
                            <table class="table table-clean align-middle">
                                <thead>
                                    <tr>
                                        <th>Pesanan</th>
                                        <th>Toko</th>
                                        <th>Status</th>
                                        <th class="text-end">Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($paymentTransaction->orders as $order)
                                        <tr>
                                            <td><span class="cell-main">{{ $order->order_number }}</span></td>
                                            <td>{{ $order->store->name }}</td>
                                            <td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                                            <td class="text-end cell-money">{{ rupiah($order->total) }}</td>
                                            <td class="text-end"><a href="{{ route('orders.show', $order) }}" class="btn btn-ghost btn-sm">Detail</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="detail-panel">
                        <h2><i class="bi bi-diagram-3"></i> Alokasi dana per penjual</h2>
                        <div class="table-responsive">
                            <table class="table table-clean align-middle">
                                <thead>
                                    <tr>
                                        <th>Toko</th>
                                        <th class="text-end">Nilai pesanan</th>
                                        <th class="text-end">Komisi</th>
                                        <th class="text-end">Diterima penjual</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($paymentTransaction->allocations as $allocation)
                                        <tr>
                                            <td>
                                                <span class="cell-main">{{ $allocation->store->name }}</span>
                                                <span class="cell-sub">{{ $allocation->method->shortLabel() }}</span>
                                            </td>
                                            <td class="text-end">{{ rupiah($allocation->gross_amount) }}</td>
                                            <td class="text-end">{{ rupiah($allocation->platform_commission) }}</td>
                                            <td class="text-end cell-money">{{ rupiah($allocation->net_amount) }}</td>
                                            <td><span class="{{ $allocation->status->badgeClass() }}">{{ $allocation->status->label() }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <aside>
                    <div class="payment-panel mb-3">
                        <h2>Tindakan pembayaran</h2>
                        <p class="payment-sub">{{ $paymentTransaction->status->description() }}</p>

                        @if ($isPending && $paymentTransaction->snap_redirect_url)
                            <a href="{{ $paymentTransaction->snap_redirect_url }}" class="btn btn-brand w-100" rel="noopener">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Lanjutkan ke Midtrans
                            </a>
                        @endif

                        @if ($isPending)
                            <form action="{{ route('payments.sync', $paymentTransaction) }}" method="POST" class="mt-2">
                                @csrf
                                <button type="submit" class="btn btn-outline-brand w-100"><i class="bi bi-arrow-repeat me-1"></i> Cek status terbaru</button>
                            </form>
                        @endif

                        @if ($canRetry && (! $isPending || ! $paymentTransaction->snap_redirect_url || $latestAttempt?->status === 'failed'))
                            <form action="{{ route('payments.retry', $paymentTransaction) }}" method="POST" class="mt-2" onsubmit="return confirm('Buat percobaan pembayaran baru? Stok pesanan akan dipesan ulang bila transaksi sebelumnya sudah ditutup.')">
                                @csrf
                                <button type="submit" class="btn btn-brand w-100"><i class="bi bi-arrow-clockwise me-1"></i> Buat pembayaran baru</button>
                            </form>
                        @endif

                        @if ($paymentTransaction->status === \App\Enums\PaymentTransactionStatus::Refunded)
                            <div class="alert alert-warning py-2 px-3 mt-3 mb-0" style="font-size:.72rem;">
                                Pembayaran telah dikembalikan. Hubungi admin bila pesanan perlu diproses ulang.
                            </div>
                        @elseif ($paymentTransaction->isTerminal())
                            <div class="alert alert-warning py-2 px-3 mt-3 mb-0" style="font-size:.72rem;">
                                Transaksi sudah {{ strtolower($paymentTransaction->status->label()) }}. Buat pembayaran baru untuk melanjutkan pesanan yang sama.
                            </div>
                        @endif

                        @if ($isPaid)
                            <div class="alert alert-success py-2 px-3 mt-3 mb-0" style="font-size:.72rem;">
                                <i class="bi bi-check-circle-fill me-1"></i> Pembayaran lunas. Seluruh pesanan terkait sudah ditandai dibayar.
                            </div>
                        @endif

                        <a href="{{ route('orders.index') }}" class="btn btn-ghost w-100 mt-2"><i class="bi bi-arrow-left me-1"></i> Kembali ke pesanan</a>
                    </div>

                    <div class="detail-panel">
                        <h2><i class="bi bi-clock-history"></i> Percobaan pembayaran</h2>
                        <div class="detail-list">
                            @forelse ($paymentTransaction->attempts as $attempt)
                                <div>
                                    <span class="label">Percobaan {{ $attempt->attempt_number }}</span>
                                    <span class="value">{{ $attempt->status }} · {{ $attempt->created_at ? tanggal_indonesia($attempt->created_at, true) : '-' }}</span>
                                </div>
                            @empty
                                <div><span class="label">Belum ada</span><span class="value">Tautan Midtrans belum dibuat.</span></div>
                            @endforelse
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
