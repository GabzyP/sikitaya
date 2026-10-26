@extends('layouts.dashboard')

@section('title', 'Pesanan '.$order->order_number)
@section('eyebrow', 'Transaksi')
@section('page_title', 'Detail pesanan')

@php
    $steps = [
        ['status' => \App\Enums\OrderStatus::Menunggu, 'date' => $order->created_at],
        ['status' => \App\Enums\OrderStatus::Dibayar, 'date' => $order->paid_at ?? ($order->payment_method === \App\Enums\PaymentMethod::Cod && $order->status !== \App\Enums\OrderStatus::Menunggu ? $order->updated_at : null)],
        ['status' => \App\Enums\OrderStatus::Dikirim, 'date' => $order->shipped_at],
        ['status' => \App\Enums\OrderStatus::Selesai, 'date' => $order->completed_at],
    ];
    $currentStep = $order->statusStepIndex();
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $order->order_number }}</h1>
            <p>Dibuat {{ tanggal_indonesia($order->created_at, true) }} oleh {{ $order->user->name }}.</p>
        </div>
        <div class="actions">
            <a href="{{ route('seller.orders.index') }}" class="btn btn-ghost btn-sm btn-dash"><i class="bi bi-arrow-left me-1"></i> Semua pesanan</a>
            <a href="{{ route('seller.orders.index', ['status' => $order->status->value]) }}" class="btn btn-outline-brand btn-sm btn-dash">Pesanan {{ strtolower($order->status->label()) }}</a>
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
                    @if ($order->payment_proof)
                        <span class="badge-soft blue"><i class="bi bi-paperclip"></i> Bukti terunggah</span>
                    @endif
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
                <h2><i class="bi bi-bag-check"></i> Produk dipesan</h2>
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
                    <div><span class="label">Subtotal produk</span><span class="value">{{ rupiah($order->subtotal) }}</span></div>
                    <div><span class="label">Ongkir {{ $order->courierLabel() }}</span><span class="value">{{ rupiah($order->shipping_cost) }}</span></div>
                    <div class="pt-2" style="border-top:1px dashed #dfe5e1;"><span class="label fw-bold">Total</span><span class="value text-brand">{{ rupiah($order->total) }}</span></div>
                </div>
            </div>

            <div class="detail-panel">
                <h2><i class="bi bi-geo-alt"></i> Pengiriman</h2>
                <div class="detail-list">
                    <div><span class="label">Penerima</span><span class="value">{{ $order->recipient_name }}</span></div>
                    <div><span class="label">Telepon</span><span class="value">{{ $order->phone }}</span></div>
                    <div><span class="label">Alamat</span><span class="value" style="max-width:70%;">{{ $order->address }}</span></div>
                    <div><span class="label">Kota tujuan</span><span class="value">{{ $order->city_name }}, {{ $order->province_name }}</span></div>
                    <div><span class="label">Kode pos</span><span class="value">{{ $order->postal_code ?: '-' }}</span></div>
                    <div><span class="label">Kurir</span><span class="value">{{ $order->courierLabel() }} · {{ $order->shipping_etd ?: '-' }}</span></div>
                    <div><span class="label">Nomor resi</span><span class="value">{{ $order->tracking_number ?: 'Belum diinput' }}</span></div>
                </div>
                @if ($order->notes)
                    <div class="order-note">
                        <i class="bi bi-chat-left-text"></i>
                        <span><strong>Catatan pembeli:</strong> {{ $order->notes }}</span>
                    </div>
                @endif
            </div>

            <div class="detail-panel">
                <h2><i class="bi bi-clock-history"></i> Riwayat pesanan</h2>
                <div class="detail-list">
                    <div><span class="label">Dibuat</span><span class="value">{{ tanggal_indonesia($order->created_at, true) }}</span></div>
                    <div><span class="label">Dibayar / dikonfirmasi</span><span class="value">{{ $order->paid_at ? tanggal_indonesia($order->paid_at, true) : ($order->status !== \App\Enums\OrderStatus::Menunggu ? 'Dikonfirmasi' : '-') }}</span></div>
                    <div><span class="label">Dikirim</span><span class="value">{{ $order->shipped_at ? tanggal_indonesia($order->shipped_at, true) : '-' }}</span></div>
                    <div><span class="label">Selesai</span><span class="value">{{ $order->completed_at ? tanggal_indonesia($order->completed_at, true) : '-' }}</span></div>
                </div>
            </div>
        </div>

        <div>
            <div class="action-panel mb-3">
                <h2><i class="bi bi-lightning-charge text-brand me-1"></i> Tindakan penjual</h2>

                @if ($order->status === \App\Enums\OrderStatus::Menunggu)
                    @if ($order->payment_method === \App\Enums\PaymentMethod::Midtrans)
                        @if ($order->paymentTransaction?->isPending())
                            <p>Pembayaran online melalui Midtrans masih menunggu. Pesanan akan otomatis berubah menjadi dibayar setelah gateway mengonfirmasi settlement.</p>
                            <div class="info-banner mb-0">
                                <i class="bi bi-hourglass-split"></i>
                                <span>Jangan memproses pengiriman sebelum status pembayaran lunas. Pembeli dapat melanjutkan pembayaran dari halaman pesanan.</span>
                            </div>
                        @else
                            <p>Pembayaran online tidak selesai atau telah ditutup. Pesanan ini tidak dapat dikonfirmasi secara manual.</p>
                            <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:.72rem;">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Status Midtrans: <strong>{{ $order->paymentTransaction?->status->label() ?? 'tidak tersedia' }}</strong>. Pembeli harus membuat pembayaran baru sebelum pesanan dapat diproses.
                            </div>
                        @endif
                    @else
                        <p>
                            @if ($order->requiresPaymentProof())
                                Periksa bukti pembayaran dari pembeli, lalu konfirmasi jika dana sudah diterima.
                            @else
                                Pesanan COD: konfirmasi pesanan untuk mulai menyiapkan pengiriman. Pembeli membayar saat pesanan diterima.
                            @endif
                        </p>

                        @if ($order->requiresPaymentProof() && ! $order->payment_proof)
                            <div class="alert alert-warning py-2 px-3" style="font-size:.72rem;">
                                <i class="bi bi-exclamation-triangle me-1"></i> Pembeli belum mengunggah bukti pembayaran. Konfirmasi belum dapat dilakukan.
                            </div>
                            <button type="button" class="btn btn-brand" disabled><i class="bi bi-check2-circle me-1"></i> Konfirmasi pembayaran</button>
                        @else
                            <form action="{{ route('seller.orders.confirm', $order) }}" method="POST" onsubmit="return confirm('Konfirmasi pembayaran pesanan ini?')">
                                @csrf
                                <button type="submit" class="btn btn-brand"><i class="bi bi-check2-circle me-1"></i> {{ $order->payment_method === \App\Enums\PaymentMethod::Cod ? 'Konfirmasi pesanan COD' : 'Konfirmasi pembayaran' }}</button>
                            </form>
                        @endif
                    @endif
                @elseif ($order->status === \App\Enums\OrderStatus::Dibayar)
                    <p>Pembayaran sudah dikonfirmasi. Masukkan nomor resi untuk menandai pesanan sebagai dikirim.</p>
                    <form action="{{ route('seller.orders.ship', $order) }}" method="POST">
                        @csrf
                        <label for="tracking_number" class="form-label">Nomor resi pengiriman</label>
                        <input type="text" id="tracking_number" name="tracking_number" value="{{ old('tracking_number') }}" class="form-control form-control-sm @error('tracking_number') is-invalid @enderror" placeholder="Contoh: JNE1234567890" maxlength="60" required>
                        @error('tracking_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-brand mt-2"><i class="bi bi-truck me-1"></i> Tandai sudah dikirim</button>
                    </form>
                @elseif ($order->status === \App\Enums\OrderStatus::Dikirim)
                    <p>Pesanan sudah dikirim{{ $order->tracking_number ? ' dengan resi '.$order->tracking_number : '' }}. Menunggu pembeli mengonfirmasi penerimaan.</p>
                    <div class="info-banner mb-0">
                        <i class="bi bi-hourglass-split"></i>
                        <span>Pembeli akan menekan tombol “Pesanan sudah saya terima” setelah paket tiba. Status pesanan berubah menjadi selesai secara otomatis.</span>
                    </div>
                @else
                    <p class="mb-2">Pesanan telah selesai pada {{ tanggal_indonesia($order->completed_at, true) }}.</p>
                    <div class="info-banner mb-0">
                        <i class="bi bi-check-circle"></i>
                        <span>Transaksi selesai. Pembayaran tercatat sebagai omzet toko Anda.</span>
                    </div>
                @endif

                @if ($errors->has('status'))
                    <div class="alert alert-danger py-2 px-3 mt-2 mb-0" style="font-size:.72rem;">{{ $errors->first('status') }}</div>
                @endif
            </div>

            <div class="payment-panel mb-3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <h2 class="mb-0">Pembayaran</h2>
                    <span class="badge-soft"><i class="bi {{ $order->payment_method->icon() }}"></i> {{ $order->payment_method->shortLabel() }}</span>
                </div>
                <div class="detail-list">
                    <div><span class="label">Metode</span><span class="value">{{ $order->payment_method->label() }}</span></div>
                    @if ($order->paymentTransaction)
                        <div><span class="label">Referensi</span><span class="value">{{ $order->paymentTransaction->reference }}</span></div>
                        <div><span class="label">Status online</span><span class="value">{{ $order->paymentTransaction->status->label() }}</span></div>
                    @endif
                    <div><span class="label">Total tagihan</span><span class="value text-brand">{{ rupiah($order->total) }}</span></div>
                    <div><span class="label">Dibayar pada</span><span class="value">{{ $order->paid_at ? tanggal_indonesia($order->paid_at, true) : '-' }}</span></div>
                </div>

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
                        <span>Pembayaran diverifikasi otomatis oleh Midtrans. Bukti unggah manual tidak diperlukan.</span>
                    </div>
                @elseif ($order->requiresPaymentProof())
                    <div class="info-banner mt-3 mb-0">
                        <i class="bi bi-paperclip"></i>
                        <span>Pembeli belum mengunggah bukti pembayaran.</span>
                    </div>
                @else
                    <div class="info-banner mt-3 mb-0">
                        <i class="bi bi-cash-coin"></i>
                        <span>Pesanan COD — pembayaran dilakukan saat pesanan diterima.</span>
                    </div>
                @endif

                @if ($order->paymentAllocation)
                    <div class="detail-list mt-3">
                        <div><span class="label">Alokasi dana</span><span class="value">{{ $order->paymentAllocation->status->label() }}</span></div>
                        <div><span class="label">Dana penjual</span><span class="value text-brand">{{ rupiah($order->paymentAllocation->net_amount) }}</span></div>
                    </div>
                @endif
            </div>

            <div class="detail-panel">
                <h2><i class="bi bi-person"></i> Pembeli</h2>
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar-xs">{{ Str::upper(Str::substr($order->user->name, 0, 1)) }}</span>
                    <div class="flex-grow-1">
                        <strong class="d-block" style="font-size:.8rem;">{{ $order->user->name }}</strong>
                        <span class="text-muted" style="font-size:.68rem;">{{ $order->user->email }}</span>
                    </div>
                </div>
                <div class="detail-list mt-3">
                    <div><span class="label">Penerima</span><span class="value">{{ $order->recipient_name }}</span></div>
                    <div><span class="label">Telepon</span><span class="value">{{ $order->phone }}</span></div>
                </div>
                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $order->phone) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm w-100 mt-2"><i class="bi bi-whatsapp me-1"></i> Hubungi pembeli</a>
            </div>
        </div>
    </div>
@endsection
