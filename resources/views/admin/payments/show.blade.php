@extends('layouts.dashboard')

@section('title', 'Pembayaran '.$paymentTransaction->reference)
@section('eyebrow', 'Keuangan')
@section('page_title', 'Detail pembayaran')

@php
    $disbursementEnabled = config('midtrans.disbursement.enabled') && filled(config('midtrans.disbursement.creator_key'));
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $paymentTransaction->reference }}</h1>
            <p>Pembayaran gabungan oleh {{ $paymentTransaction->user->name }} · {{ tanggal_indonesia($paymentTransaction->created_at, true) }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.payments.index') }}" class="btn btn-ghost btn-sm btn-dash"><i class="bi bi-arrow-left me-1"></i> Semua pembayaran</a>
            <form action="{{ route('admin.payments.sync', $paymentTransaction) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-brand btn-sm btn-dash"><i class="bi bi-arrow-repeat me-1"></i> Sinkron status</button>
            </form>
        </div>
    </div>

    <div class="order-detail-header">
        <div class="order-title">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="{{ $paymentTransaction->status->badgeClass() }}">{{ $paymentTransaction->status->label() }}</span>
                    <span class="badge-soft"><i class="bi bi-diagram-3"></i> {{ $paymentTransaction->allocation_method?->label() ?? '-' }}</span>
                    <span class="badge-soft"><i class="bi bi-credit-card"></i> Midtrans</span>
                </div>
                <div class="order-meta">{{ $paymentTransaction->status->description() }}</div>
            </div>
            <div class="text-end">
                <div class="text-muted" style="font-size:.68rem;">Total pembayaran</div>
                <div class="fs-4 fw-bold text-brand">{{ rupiah($paymentTransaction->gross_amount) }}</div>
            </div>
        </div>
    </div>

    <div class="detail-grid">
        <div>
            <div class="detail-panel">
                <h2><i class="bi bi-receipt"></i> Ringkasan transaksi</h2>
                <div class="detail-list">
                    <div><span class="label">Order ID Midtrans</span><span class="value">{{ $paymentTransaction->midtrans_order_id ?: '-' }}</span></div>
                    <div><span class="label">Transaction ID</span><span class="value">{{ $paymentTransaction->midtrans_transaction_id ?: '-' }}</span></div>
                    <div><span class="label">Payment type</span><span class="value">{{ $paymentTransaction->payment_type ?: '-' }}</span></div>
                    <div><span class="label">Fraud status</span><span class="value">{{ $paymentTransaction->fraud_status ?: '-' }}</span></div>
                    <div><span class="label">Nilai pesanan</span><span class="value">{{ rupiah($paymentTransaction->gross_amount) }}</span></div>
                    <div><span class="label">Komisi platform</span><span class="value">{{ rupiah($paymentTransaction->platform_commission) }}</span></div>
                    <div><span class="label">Biaya Midtrans</span><span class="value">{{ $paymentTransaction->midtrans_fee > 0 ? rupiah($paymentTransaction->midtrans_fee) : 'Ditanggung platform' }}</span></div>
                    <div><span class="label">Penanggung biaya</span><span class="value">{{ $paymentTransaction->fee_bearer }}</span></div>
                    <div><span class="label">Kedaluwarsa</span><span class="value">{{ $paymentTransaction->expires_at ? tanggal_indonesia($paymentTransaction->expires_at, true) : '-' }}</span></div>
                    <div><span class="label">Dibayar</span><span class="value">{{ $paymentTransaction->paid_at ? tanggal_indonesia($paymentTransaction->paid_at, true) : '-' }}</span></div>
                    <div><span class="label">Notifikasi terakhir</span><span class="value">{{ $paymentTransaction->notified_at ? tanggal_indonesia($paymentTransaction->notified_at, true) : '-' }}</span></div>
                    <div><span class="label">Jumlah notifikasi</span><span class="value">{{ $paymentTransaction->notification_count }}×</span></div>
                </div>

                @if ($paymentTransaction->failure_reason)
                    <div class="alert alert-warning py-2 px-3 mt-3 mb-0" style="font-size:.74rem;">
                        <i class="bi bi-exclamation-triangle me-1"></i> {{ $paymentTransaction->failure_reason }}
                    </div>
                @endif

                @if ($paymentTransaction->stock_restored_at)
                    <div class="info-banner mt-3 mb-0">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Stok dipulihkan pada {{ tanggal_indonesia($paymentTransaction->stock_restored_at, true) }}. Pemulihan tidak akan diulang untuk transaksi terminal yang sama.</span>
                    </div>
                @endif
            </div>

            <div class="detail-panel">
                <h2><i class="bi bi-bag-check"></i> Pesanan terkait</h2>
                <div class="table-responsive">
                    <table class="table table-clean align-middle">
                        <thead>
                            <tr>
                                <th>Pesanan</th>
                                <th>Toko / Penjual</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-end">Ongkir</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($paymentTransaction->orders as $order)
                                <tr>
                                    <td class="cell-main">{{ $order->order_number }}</td>
                                    <td>
                                        <div class="cell-main">{{ $order->store->name }}</div>
                                        <div class="cell-sub">{{ $order->store->owner?->name }}</div>
                                    </td>
                                    <td class="text-end cell-money">{{ rupiah($order->subtotal) }}</td>
                                    <td class="text-end cell-money">{{ rupiah($order->shipping_cost) }}</td>
                                    <td class="text-end cell-money">{{ rupiah($order->total) }}</td>
                                    <td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                                    <td class="text-end"><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-ghost btn-sm">Detail</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="detail-panel">
                <h2><i class="bi bi-diagram-3"></i> Alokasi dana</h2>

                @foreach ($paymentTransaction->allocations as $allocation)
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                            <div>
                                <div class="fw-bold">{{ $allocation->store->name }}</div>
                                <div class="cell-sub">{{ $allocation->seller?->name }} · {{ $allocation->order->order_number }} · {{ $allocation->method->label() }}</div>
                            </div>
                            <span class="{{ $allocation->status->badgeClass() }}">{{ $allocation->status->label() }}</span>
                        </div>

                        <div class="row g-2 mt-2">
                            <div class="col-sm-6 col-lg-3"><div class="cell-sub">Nilai pesanan</div><div class="cell-money">{{ rupiah($allocation->gross_amount) }}</div></div>
                            <div class="col-sm-6 col-lg-3"><div class="cell-sub">Komisi</div><div class="cell-money">{{ rupiah($allocation->platform_commission) }}</div></div>
                            <div class="col-sm-6 col-lg-3"><div class="cell-sub">Diterima penjual</div><div class="cell-money text-brand">{{ rupiah($allocation->net_amount) }}</div></div>
                            <div class="col-sm-6 col-lg-3"><div class="cell-sub">Sudah dicairkan</div><div class="cell-money">{{ rupiah($allocation->disbursed_amount) }}</div></div>
                        </div>

                        @if ($allocation->isSplit())
                            <div class="info-banner mt-3 mb-0">
                                <i class="bi bi-shield-check"></i>
                                <span>Dialokasikan otomatis sebagai transaction split ke merchant {{ $allocation->merchant_id }}. Status mengikuti settlement Midtrans.</span>
                            </div>
                        @else
                            <div class="detail-list mt-3">
                                <div><span class="label">Bank</span><span class="value">{{ strtoupper($allocation->payout_bank_code ?: '-') }} · {{ $allocation->maskedPayoutAccountNumber() }}</span></div>
                                <div><span class="label">Nama pemilik</span><span class="value">{{ $allocation->payout_account_name ?: '-' }}</span></div>
                                <div><span class="label">Referensi pencairan</span><span class="value">{{ $allocation->midtrans_reference ?: '-' }}</span></div>
                                <div><span class="label">Status gateway</span><span class="value">{{ $allocation->midtrans_status ?: '-' }}</span></div>
                                <div><span class="label">Percobaan</span><span class="value">{{ $allocation->attempt_count }}×</span></div>
                                <div><span class="label">Disetujui</span><span class="value">{{ $allocation->approved_at ? tanggal_indonesia($allocation->approved_at, true) : '-' }}</span></div>
                                <div><span class="label">Selesai</span><span class="value">{{ $allocation->completed_at ? tanggal_indonesia($allocation->completed_at, true) : '-' }}</span></div>
                            </div>

                            @if ($allocation->last_error)
                                <div class="alert alert-warning py-2 px-3 mt-2 mb-0" style="font-size:.72rem;">{{ $allocation->last_error }}</div>
                            @endif

                            <div class="d-flex flex-wrap gap-2 mt-3">
                                @if ($allocation->canBeApproved())
                                    <form action="{{ route('admin.allocations.approve', $allocation) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-brand btn-sm"><i class="bi bi-check2-circle me-1"></i> Setujui alokasi</button>
                                    </form>
                                @endif

                                @if ($allocation->canBeSent())
                                    @if ($disbursementEnabled)
                                        <form action="{{ route('admin.allocations.send', $allocation) }}" method="POST" onsubmit="return confirm('Kirim dana {{ rupiah($allocation->net_amount) }} ke rekening toko melalui Midtrans?')">
                                            @csrf
                                            <button type="submit" class="btn btn-brand btn-sm"><i class="bi bi-send me-1"></i> Cairkan via Midtrans</button>
                                        </form>
                                    @else
                                        <span class="badge-soft red"><i class="bi bi-shield-exclamation"></i> Disbursement API belum aktif</span>
                                    @endif
                                @endif

                                @if ($allocation->midtrans_reference)
                                    <form action="{{ route('admin.allocations.sync', $allocation) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-brand btn-sm"><i class="bi bi-arrow-repeat me-1"></i> Sinkron pencairan</button>
                                    </form>
                                @endif
                            </div>
                        @endif

                        @if ($allocation->events->isNotEmpty())
                            <details class="mt-3">
                                <summary class="cell-sub">Riwayat audit ({{ $allocation->events->count() }})</summary>
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm table-clean mb-0">
                                        <thead>
                                            <tr><th>Waktu</th><th>Aksi</th><th>Status</th><th>Pelaku</th><th>Catatan</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($allocation->events->sortByDesc('created_at') as $event)
                                                <tr>
                                                    <td class="cell-sub">{{ tanggal_indonesia($event->created_at, true) }}</td>
                                                    <td>{{ $event->action }}</td>
                                                    <td class="cell-sub">{{ $event->status_before ?: '-' }} → {{ $event->status_after ?: '-' }}</td>
                                                    <td>{{ $event->actor?->name ?? 'Sistem' }}</td>
                                                    <td class="cell-sub">{{ $event->message }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <div class="detail-panel">
                <h2><i class="bi bi-person"></i> Pembeli</h2>
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar-xs">{{ Str::upper(Str::substr($paymentTransaction->user->name, 0, 1)) }}</span>
                    <div>
                        <strong class="d-block" style="font-size:.8rem;">{{ $paymentTransaction->user->name }}</strong>
                        <span class="text-muted" style="font-size:.68rem;">{{ $paymentTransaction->user->email }}</span>
                    </div>
                </div>
            </div>

            <div class="detail-panel">
                <h2><i class="bi bi-clock-history"></i> Percobaan Snap</h2>
                <div class="detail-list">
                    @forelse ($paymentTransaction->attempts as $attempt)
                        <div>
                            <span class="label">Ke-{{ $attempt->attempt_number }}</span>
                            <span class="value">{{ $attempt->status }} · {{ $attempt->created_at ? tanggal_indonesia($attempt->created_at, true) : '-' }}</span>
                        </div>
                    @empty
                        <div><span class="label">Belum ada</span><span class="value">Snap belum dibuat.</span></div>
                    @endforelse
                </div>
            </div>

            <div class="detail-panel">
                <h2><i class="bi bi-hdd-network"></i> Log webhook</h2>
                <div class="detail-list">
                    @forelse ($paymentTransaction->webhookLogs as $log)
                        <div>
                            <span class="label">{{ $log->transaction_status ?: '-' }} · {{ $log->created_at ? tanggal_indonesia($log->created_at, true) : '-' }}</span>
                            <span class="value">
                                {{ $log->signature_valid ? 'Signature valid' : 'Signature tidak valid' }} ·
                                {{ $log->processed ? 'diproses' : 'tidak diproses' }}
                                @if ($log->message)<br><span class="cell-sub">{{ $log->message }}</span>@endif
                            </span>
                        </div>
                    @empty
                        <div><span class="label">Belum ada</span><span class="value">Belum ada callback Midtrans.</span></div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
