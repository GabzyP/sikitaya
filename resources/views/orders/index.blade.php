@extends('layouts.app')

@section('title', 'Pesanan Saya')

@php
    $statusCounts = \App\Enums\OrderStatus::cases();
@endphp

@section('content')
    <section class="page-banner page-banner-light">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pesanan</li>
                </ol>
            </nav>
            <h1>Pesanan saya</h1>
            <p>Pantau status pembayaran dan pengiriman seluruh pesanan Anda di Sikitaya.</p>
        </div>
    </section>

    <section class="page-section">
        <div class="container">
            <div class="orders-layout">
                <aside class="orders-filter">
                    <a class="{{ empty($filters['status'] ?? null) ? 'active' : '' }}" href="{{ route('orders.index') }}">
                        Semua pesanan <span>{{ $counts->sum() }}</span>
                    </a>
                    @foreach ($statusCounts as $status)
                        <a class="{{ ($filters['status'] ?? null) === $status->value ? 'active' : '' }}" href="{{ route('orders.index', ['status' => $status->value]) }}">
                            {{ $status->label() }} <span>{{ $counts[$status->value] ?? 0 }}</span>
                        </a>
                    @endforeach
                </aside>

                <div>
                    @if ($orders->isEmpty())
                        <div class="empty-state">
                            <span class="empty-icon"><i class="bi bi-receipt"></i></span>
                            <h2>{{ filled($filters['status'] ?? null) ? 'Tidak ada pesanan dengan status ini' : 'Belum ada pesanan' }}</h2>
                            <p>Yuk mulai belanja produk lokal Kota Medan. Pesanan Anda akan muncul di halaman ini.</p>
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <a href="{{ route('products.index') }}" class="btn btn-brand">Mulai belanja</a>
                                @if (filled($filters['status'] ?? null))
                                    <a href="{{ route('orders.index') }}" class="btn btn-outline-brand">Lihat semua pesanan</a>
                                @endif
                            </div>
                        </div>
                    @else
                        @foreach ($orders as $order)
                            <article class="order-card">
                                <div class="order-card-head">
                                    <div>
                                        <div class="order-number">{{ $order->order_number }}</div>
                                        <div class="order-date">{{ tanggal_indonesia($order->created_at, true) }}</div>
                                    </div>
                                    <span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                                </div>

                                <div class="order-card-store">
                                    <i class="bi bi-shop"></i>
                                    <a href="{{ route('stores.show', $order->store) }}" class="text-reset fw-semibold">{{ $order->store->name }}</a>
                                    <span class="ms-auto">{{ $order->items->sum('quantity') }} item · {{ $order->courierLabel() }}</span>
                                </div>

                                <div class="order-card-items">
                                    @foreach ($order->items as $item)
                                        <div class="order-line">
                                            <img src="{{ $item->image_url }}" alt="{{ $item->product_name }}">
                                            <div>
                                                <div class="name">{{ $item->product_name }}</div>
                                                <div class="qty">{{ $item->quantity }} × {{ rupiah($item->product_price) }}</div>
                                            </div>
                                            <span class="line-total">{{ rupiah($item->subtotal) }}</span>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="order-card-foot">
                                    <span class="order-total">Total pesanan <strong>{{ rupiah($order->total) }}</strong></span>
                                    <div class="actions">
                                        @if ($order->canBePaidByBuyer())
                                            <a href="{{ route('orders.show', $order) }}" class="btn btn-brand btn-sm">
                                                <i class="bi bi-upload me-1"></i> {{ $order->payment_proof ? 'Lihat pembayaran' : 'Bayar sekarang' }}
                                            </a>
                                        @endif
                                        <a href="{{ route('orders.show', $order) }}" class="btn btn-outline-brand btn-sm">
                                            Lihat detail <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach

                        <div class="mt-3 d-flex justify-content-center">
                            {{ $orders->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
