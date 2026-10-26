@extends('layouts.dashboard')

@section('title', 'Pesanan '.$order->order_number)
@section('eyebrow', 'Transaksi')
@section('page_title', 'Detail pesanan')

@php
    $steps = [
        ['status' => \App\Enums\OrderStatus::Menunggu, 'date' => $order->created_at],
        ['status' => \App\Enums\OrderStatus::Dibayar, 'date' => $order->paid_at],
        ['status' => \App\Enums\OrderStatus::Dikirim, 'date' => $order->shipped_at],
        ['status' => \App\Enums\OrderStatus::Selesai, 'date' => $order->completed_at],
    ];
    $currentStep = $order->statusStepIndex();
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $order->order_number }}</h1>
            <p>Pesanan dari {{ $order->store->name }} untuk {{ $order->user->name }}.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.orders.index') }}" class="btn btn-ghost btn-sm btn-dash"><i class="bi bi-arrow-left me-1"></i> Semua pesanan</a>
            <a href="{{ route('admin.orders.index', ['store' => $order->store_id]) }}" class="btn btn-outline-brand btn-sm btn-dash"><i class="bi bi-shop me-1"></i> Pesanan toko ini</a>
        </div>
    </div>

    <div class="order-detail-header">
        <div class="order-title">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                    @if ($order->paymentTransaction)
                        <span class="{{ $order->paymentTransaction->status->badgeClass() }}">{{ $order->paymentTransaction->status->label() }}</span>
                    @endif
                    <span class="badge-soft"><i class="bi {{ $order->payment_method->icon() }}"></i> {{ $order->payment_method->label() }}</span>
                </div>
                <div class="order-meta">{{ $order->status->description() }}</div>
            </div>
            <div class="text-end">
                <div class="text-muted" style="font-size:.68rem;">Total pesanan</div>
                <div class="fs-4 fw-bold text-brand">{{ rupiah($order->total) }}</div>
            </div>
        </div>

        <div class="order-progress">
            @foreach ($steps as $index => $step)
                @php
                    $isCurrent = $index === $currentStep;
                    $isDone = $index < $currentStep || $order->status === \App\Enums\OrderStatus::Selesai;
                @endphp
                <div class="progress-step {{ $isDone ? 'done' : '' }} {{ $isCurrent ? 'current' : '' }}">
                    <span class="step-dot">
                        @if ($isDone && ! $isCurrent)
                            <i class="bi bi-check-lg"></i>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </span>
                    <strong>{{ $step['status']->label() }}</strong>
                    @if ($step['date'])
                        <span class="step-date">{{ tanggal_indonesia($step['date'], true) }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="detail-grid">
        <div>
            <div class="detail-panel">
                <h2><i class="bi bi-bag-check"></i> Produk pesanan</h2>
                <div class="table-responsive">
                    <table class="table table-clean">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th class="text-end">Harga</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ $item->image_url }}" alt="{{ $item->product_name }}" class="product-thumb">
                                            <div>
                                                <div class="cell-main">{{ $item->product_name }}</div>
                                                <div class="cell-sub">{{ berat($item->weight) }} / item</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end">{{ rupiah($item->product_price) }}</td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-end cell-money">{{ rupiah($item->subtotal) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="detail-list mt-2">
                    <div><span class="label">Subtotal</span><span class="value">{{ rupiah($order->subtotal) }}</span></div>
                    <div><span class="label">Ongkir</span><span class="value">{{ rupiah($order->shipping_cost) }}</span></div>
                    <div class="pt-2" style="border-top:1px dashed #dfe5e1;"><span class="label fw-bold">Total</span><span class="value text-brand">{{ rupiah($order->total) }}</span></div>
                </div>
            </div>

            <div class="detail-panel">
                <h2><i class="bi bi-geo-alt"></i> Alamat & pengiriman</h2>
                <div class="detail-list">
                    <div><span class="label">Penerima</span><span class="value">{{ $order->recipient_name }}</span></div>
                    <div><span class="label">Telepon</span><span class="value">{{ $order->phone }}</span></div>
                    <div><span class="label">Alamat</span><span class="value" style="max-width:70%;">{{ $order->address }}</span></div>
                    <div><span class="label">Kota tujuan</span><span class="value">{{ $order->city_name }}, {{ $order->province_name }}</span></div>
                    <div><span class="label">Kode pos</span><span class="value">{{ $order->postal_code ?: '-' }}</span></div>
                    <div><span class="label">Kurir</span><span class="value">{{ $order->courierLabel() }} · {{ $order->shipping_etd ?: '-' }}</span></div>
                    <div><span class="label">Nomor resi</span><span class="value">{{ $order->tracking_number ?: '-' }}</span></div>
                </div>
                @if ($order->notes)
                    <div class="order-note">
                        <i class="bi bi-chat-left-text"></i>
                        <span><strong>Catatan:</strong> {{ $order->notes }}</span>
                    </div>
                @endif
            </div>

            <div class="detail-panel">
                <h2><i class="bi bi-clock-history"></i> Riwayat</h2>
                <div class="detail-list">
                    <div><span class="label">Dibuat</span><span class="value">{{ tanggal_indonesia($order->created_at, true) }}</span></div>
                    <div><span class="label">Dibayar / dikonfirmasi</span><span class="value">{{ $order->paid_at ? tanggal_indonesia($order->paid_at, true) : '-' }}</span></div>
                    <div><span class="label">Dikirim</span><span class="value">{{ $order->shipped_at ? tanggal_indonesia($order->shipped_at, true) : '-' }}</span></div>
                    <div><span class="label">Selesai</span><span class="value">{{ $order->completed_at ? tanggal_indonesia($order->completed_at, true) : '-' }}</span></div>
                </div>
            </div>
        </div>

        <div>
            <div class="detail-panel">
                <h2><i class="bi bi-shop"></i> Toko</h2>
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar-store">
                        @if ($order->store->logo_url)
                            <img src="{{ $order->store->logo_url }}" alt="{{ $order->store->name }}">
                        @else
                            {{ $order->store->initials }}
                        @endif
                    </span>
                    <div class="flex-grow-1">
                        <strong class="d-block" style="font-size:.8rem;">{{ $order->store->name }}</strong>
                        <span class="text-muted" style="font-size:.68rem;">{{ $order->store->owner?->name }}</span>
                    </div>
                    <a href="{{ route('stores.show', $order->store) }}" target="_blank" class="btn btn-outline-brand btn-sm">Lihat</a>
                </div>
                <div class="detail-list mt-3">
                    <div><span class="label">Lokasi</span><span class="value">{{ $order->store->city_name }}</span></div>
                    <div><span class="label">Kontak</span><span class="value">{{ $order->store->phone ?: '-' }}</span></div>
                </div>
            </div>

            <div class="payment-panel mb-3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <h2 class="mb-0">Pembayaran</h2>
                    @if ($order->paymentTransaction)
                        <span class="{{ $order->paymentTransaction->status->badgeClass() }}">{{ $order->paymentTransaction->status->label() }}</span>
                    @else
                        <span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                    @endif
                </div>
                <div class="detail-list">
                    <div><span class="label">Metode</span><span class="value">{{ $order->payment_method->label() }}</span></div>
                    @if ($order->paymentTransaction)
                        <div><span class="label">Referensi</span><span class="value">{{ $order->paymentTransaction->reference }}</span></div>
                    @endif
                    <div><span class="label">Total</span><span class="value text-brand">{{ rupiah($order->total) }}</span></div>
                    <div><span class="label">Dibayar pada</span><span class="value">{{ $order->paid_at ? tanggal_indonesia($order->paid_at, true) : '-' }}</span></div>
                </div>

                @if ($order->paymentTransaction)
                    <a href="{{ route('admin.payments.show', $order->paymentTransaction) }}" class="btn btn-outline-brand btn-sm w-100 mt-3">
                        <i class="bi bi-diagram-3 me-1"></i> Kelola pembayaran gabungan
                    </a>
                @endif

                @if ($order->payment_proof)
                    <div class="proof-preview mt-3 mb-0">
                        @if ($order->isProofPdf())
                            <i class="bi bi-file-earmark-pdf"></i>
                        @else
                            <img src="{{ $order->paymentProofUrl() }}" alt="Bukti pembayaran">
                        @endif
                        <div>
                            <div class="fw-semibold">Bukti pembayaran</div>
                            <div class="text-muted">{{ $order->payment_proof_uploaded_at ? tanggal_indonesia($order->payment_proof_uploaded_at, true) : '' }}</div>
                        </div>
                        <a href="{{ $order->paymentProofUrl() }}" target="_blank" rel="noopener" class="btn btn-outline-brand btn-sm ms-auto">Buka</a>
                    </div>
                @elseif ($order->payment_method === \App\Enums\PaymentMethod::Midtrans)
                    <div class="info-banner mt-3 mb-0">
                        <i class="bi bi-credit-card-2-front"></i>
                        <span>Pembayaran diverifikasi otomatis oleh Midtrans. Tidak ada bukti unggah manual.</span>
                    </div>
                @elseif ($order->requiresPaymentProof())
                    <div class="info-banner mt-3 mb-0">
                        <i class="bi bi-paperclip"></i>
                        <span>Belum ada bukti pembayaran yang diunggah.</span>
                    </div>
                @else
                    <div class="info-banner mt-3 mb-0">
                        <i class="bi bi-cash-coin"></i>
                        <span>Pesanan COD — pembayaran dilakukan saat diterima.</span>
                    </div>
                @endif
            </div>

            <div class="detail-panel">
                <h2><i class="bi bi-person"></i> Pembeli</h2>
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar-xs">{{ Str::upper(Str::substr($order->user->name, 0, 1)) }}</span>
                    <div>
                        <strong class="d-block" style="font-size:.8rem;">{{ $order->user->name }}</strong>
                        <span class="text-muted" style="font-size:.68rem;">{{ $order->user->email }}</span>
                    </div>
                </div>
                <div class="detail-list mt-3">
                    <div><span class="label">Telepon</span><span class="value">{{ $order->phone }}</span></div>
                    <div><span class="label">Akun aktif</span><span class="value">{{ $order->user->is_active ? 'Ya' : 'Tidak' }}</span></div>
                </div>
            </div>

            <div class="info-banner mb-0">
                <i class="bi bi-shield-lock"></i>
                <span>Admin hanya memantau pesanan. Konfirmasi pembayaran dan pengiriman dilakukan oleh penjual terkait.</span>
            </div>
        </div>
    </div>
@endsection
