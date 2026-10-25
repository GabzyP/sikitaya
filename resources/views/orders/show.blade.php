@extends('layouts.app')

@section('title', 'Pesanan '.$order->order_number)

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
    <section class="page-banner page-banner-light">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">Pesanan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $order->order_number }}</li>
                </ol>
            </nav>
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <h1 class="mb-1">Detail pesanan</h1>
                    <p class="mb-0">{{ $order->order_number }} · dibuat {{ tanggal_indonesia($order->created_at, true) }}</p>
                </div>
                <a href="{{ route('orders.index') }}" class="btn btn-outline-brand btn-sm"><i class="bi bi-arrow-left me-1"></i> Semua pesanan</a>
            </div>
        </div>
    </section>

    <section class="page-section">
        <div class="container">
            <div class="detail-panel" style="padding:1.25rem 1.4rem;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                    <div class="d-flex align-items-center gap-2">
                        <span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                        <span class="text-muted" style="font-size:.72rem;">{{ $order->status->description() }}</span>
                    </div>
                    <span class="order-total">Total <strong>{{ rupiah($order->total) }}</strong></span>
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
                            <table class="table table-clean align-middle">
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

                        <div class="detail-list mt-3">
                            <div><span class="label">Subtotal produk</span><span class="value">{{ rupiah($order->subtotal) }}</span></div>
                            <div><span class="label">Ongkos kirim ({{ $order->courierLabel() }})</span><span class="value">{{ rupiah($order->shipping_cost) }}</span></div>
                            <div class="pt-2" style="border-top:1px dashed #dfe5e1;"><span class="label fw-bold">Total pembayaran</span><span class="value text-brand fs-6">{{ rupiah($order->total) }}</span></div>
                        </div>
                    </div>

                    <div class="detail-panel">
                        <h2><i class="bi bi-geo-alt"></i> Alamat pengiriman</h2>
                        <div class="detail-list">
                            <div><span class="label">Penerima</span><span class="value">{{ $order->recipient_name }}</span></div>
                            <div><span class="label">Telepon</span><span class="value">{{ $order->phone }}</span></div>
                            <div><span class="label">Alamat</span><span class="value" style="max-width:70%;">{{ $order->address }}</span></div>
                            <div><span class="label">Kota / kabupaten</span><span class="value">{{ $order->city_name }}, {{ $order->province_name }}</span></div>
                            <div><span class="label">Kode pos</span><span class="value">{{ $order->postal_code ?: '-' }}</span></div>
                        </div>
                        @if ($order->notes)
                            <div class="order-note">
                                <i class="bi bi-chat-left-text"></i>
                                <span><strong>Catatan:</strong> {{ $order->notes }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="detail-panel">
                        <h2><i class="bi bi-truck"></i> Informasi pengiriman</h2>
                        <div class="detail-list">
                            <div><span class="label">Kurir</span><span class="value">{{ $order->courierLabel() }}</span></div>
                            <div><span class="label">Estimasi tiba</span><span class="value">{{ $order->shipping_etd ?: '-' }}</span></div>
                            <div><span class="label">Nomor resi</span><span class="value">{{ $order->tracking_number ?: 'Belum tersedia' }}</span></div>
                            <div><span class="label">Dikirim pada</span><span class="value">{{ $order->shipped_at ? tanggal_indonesia($order->shipped_at, true) : '-' }}</span></div>
                            <div><span class="label">Selesai pada</span><span class="value">{{ $order->completed_at ? tanggal_indonesia($order->completed_at, true) : '-' }}</span></div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="payment-panel mb-3">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <h2>Pembayaran</h2>
                                <p class="payment-sub">{{ $order->payment_method->label() }}</p>
                            </div>
                            <span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                        </div>

                        @if ($order->payment_method === \App\Enums\PaymentMethod::Transfer)
                            <div class="bank-card">
                                <div class="bank-name">Bank BCA · Rekening simulasi</div>
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <div>
                                        <div class="account-number">5271 8890 12</div>
                                        <div class="account-owner">a.n. {{ $order->store->name }}</div>
                                    </div>
                                    <button type="button" class="btn-copy" data-copy="5271889012"><i class="bi bi-copy"></i> Salin</button>
                                </div>
                            </div>
                            <p class="mb-2" style="font-size:.72rem;color:var(--muted);">Transfer sebesar <strong class="text-brand">{{ rupiah($order->total) }}</strong>, lalu unggah bukti transfer di bawah.</p>
                        @elseif ($order->payment_method === \App\Enums\PaymentMethod::Qris)
                            <div class="qris-card">
                                <img src="{{ asset('images/qris-demo.svg') }}" alt="Kode QRIS simulasi">
                                <p>QRIS simulasi · nominal {{ rupiah($order->total) }}</p>
                            </div>
                        @else
                            <div class="info-note mb-3">
                                <i class="bi bi-cash-coin"></i>
                                <span>Pesanan COD tidak memerlukan bukti bayar. Siapkan <strong>{{ rupiah($order->total) }}</strong> untuk dibayarkan kepada kurir saat pesanan diterima.</span>
                            </div>
                        @endif

                        @if ($order->canBePaidByBuyer())
                            <div class="proof-upload">
                                <h3>{{ $order->payment_proof ? 'Bukti pembayaran terunggah' : 'Unggah bukti pembayaran' }}</h3>
                                @if ($order->payment_proof)
                                    <div class="proof-preview">
                                        @if ($order->isProofPdf())
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        @else
                                            <img src="{{ $order->paymentProofUrl() }}" alt="Bukti pembayaran">
                                        @endif
                                        <div>
                                            <div class="fw-semibold">Bukti sudah diunggah</div>
                                            <div class="text-muted">{{ $order->payment_proof_uploaded_at ? tanggal_indonesia($order->payment_proof_uploaded_at, true) : '' }}</div>
                                        </div>
                                        <a href="{{ $order->paymentProofUrl() }}" target="_blank" rel="noopener" class="btn btn-outline-brand btn-sm ms-auto">Lihat</a>
                                    </div>
                                    <p class="mb-2">Penjual akan memverifikasi pembayaran Anda. Anda masih dapat mengganti bukti jika ada kesalahan.</p>
                                @else
                                    <p>Pastikan nominal dan nama pengirim sesuai. Bukti akan diverifikasi oleh penjual.</p>
                                @endif

                                <form action="{{ route('orders.proof', $order) }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <label for="payment_proof" class="form-label">{{ $order->payment_proof ? 'Ganti bukti pembayaran' : 'Pilih file bukti' }}</label>
                                    <input type="file" id="payment_proof" name="payment_proof" class="form-control form-control-sm @error('payment_proof') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                                    @error('payment_proof')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text mb-2">JPG, PNG, WEBP, atau PDF · maksimal 2 MB.</div>
                                    <button type="submit" class="btn btn-brand w-100"><i class="bi bi-cloud-arrow-up me-1"></i> Kirim bukti pembayaran</button>
                                </form>
                            </div>
                        @elseif ($order->payment_proof)
                            <div class="proof-preview">
                                @if ($order->isProofPdf())
                                    <i class="bi bi-file-earmark-pdf"></i>
                                @else
                                    <img src="{{ $order->paymentProofUrl() }}" alt="Bukti pembayaran">
                                @endif
                                <div>
                                    <div class="fw-semibold">Bukti pembayaran</div>
                                    <div class="text-muted">{{ $order->payment_proof_uploaded_at ? tanggal_indonesia($order->payment_proof_uploaded_at, true) : '' }}</div>
                                </div>
                                <a href="{{ $order->paymentProofUrl() }}" target="_blank" rel="noopener" class="btn btn-outline-brand btn-sm ms-auto">Lihat</a>
                            </div>
                        @endif

                        @if ($order->status === \App\Enums\OrderStatus::Menunggu)
                            <div class="order-note">
                                <i class="bi bi-hourglass-split"></i>
                                <span>
                                    @if ($order->requiresPaymentProof())
                                        Menunggu verifikasi pembayaran oleh penjual. Anda akan dapat memantau status pesanan di halaman ini.
                                    @else
                                        Menunggu konfirmasi penjual. Pesanan COD akan diproses setelah dikonfirmasi.
                                    @endif
                                </span>
                            </div>
                        @elseif ($order->status === \App\Enums\OrderStatus::Dibayar)
                            <div class="alert alert-success py-2 px-3 mt-3 mb-0" style="font-size:.72rem;">
                                <i class="bi bi-check-circle me-1"></i> Pembayaran telah dikonfirmasi. Penjual akan segera mengirim pesanan Anda.
                            </div>
                        @elseif ($order->status === \App\Enums\OrderStatus::Dikirim)
                            <div class="alert alert-info py-2 px-3 mt-3 mb-2" style="font-size:.72rem;background:#eef4fb;border-color:#dbe7f5;color:#255b9b;">
                                <i class="bi bi-truck me-1"></i> Pesanan sedang dalam pengiriman{{ $order->tracking_number ? ' dengan resi '.$order->tracking_number : '' }}.
                            </div>
                            <form action="{{ route('orders.complete', $order) }}" method="POST" onsubmit="return confirm('Konfirmasi bahwa pesanan sudah Anda terima dengan baik?')">
                                @csrf
                                <button type="submit" class="btn btn-brand w-100"><i class="bi bi-check2-circle me-1"></i> Pesanan sudah saya terima</button>
                            </form>
                        @elseif ($order->status === \App\Enums\OrderStatus::Selesai)
                            <div class="alert alert-success py-2 px-3 mt-3 mb-0" style="font-size:.72rem;">
                                <i class="bi bi-check-circle-fill me-1"></i> Pesanan selesai pada {{ tanggal_indonesia($order->completed_at, true) }}. Terima kasih telah mendukung UMKM Medan!
                            </div>
                        @endif

                        @if ($order->paid_at)
                            <p class="text-muted mt-3 mb-0" style="font-size:.66rem;">Pembayaran tercatat {{ tanggal_indonesia($order->paid_at, true) }}.</p>
                        @endif
                    </div>

                    <div class="detail-panel">
                        <h2><i class="bi bi-shop"></i> Penjual</h2>
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
                                <span class="text-muted" style="font-size:.68rem;"><i class="bi bi-geo-alt"></i> {{ $order->store->city_name }}</span>
                            </div>
                            <a href="{{ route('stores.show', $order->store) }}" class="btn btn-outline-brand btn-sm">Toko</a>
                        </div>
                        @if ($order->store->phone)
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $order->store->phone) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm w-100 mt-2"><i class="bi bi-whatsapp me-1"></i> Hubungi penjual</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
