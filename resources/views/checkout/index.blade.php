@extends('layouts.app')

@section('title', 'Checkout')

@php
    $selectedCity = (string) old('city_id', 278);
    $paymentDescriptions = [
        'transfer' => 'Simulasi transfer ke rekening toko. Unggah bukti setelah pesanan dibuat.',
        'qris' => 'Simulasi QRIS. Pindai kode pada detail pesanan lalu unggah bukti.',
        'midtrans' => 'Bayar semua pesanan toko dalam satu transaksi aman melalui Midtrans.',
        'cod' => 'Bayar tunai kepada kurir saat pesanan diterima.',
    ];
    $selectedPayment = old('payment_method', $paymentMethods[0]->value ?? 'cod');
    $isDirectCheckout = ($selection['mode'] ?? 'cart') === 'direct';
    $firstProduct = $groups->first()['items']->first()->product;
@endphp

@section('content')
    <section class="page-banner page-banner-light">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                    @if ($isDirectCheckout)
                        <li class="breadcrumb-item"><a href="{{ route('products.show', $firstProduct) }}">Produk</a></li>
                    @else
                        <li class="breadcrumb-item"><a href="{{ route('cart.index') }}">Keranjang</a></li>
                    @endif
                    <li class="breadcrumb-item active" aria-current="page">Checkout</li>
                </ol>
            </nav>
            <h1>Checkout pesanan</h1>
            <p>
                @if ($isDirectCheckout)
                    Checkout langsung tanpa menyimpan produk ke keranjang. Lengkapi alamat, layanan pengiriman, dan metode pembayaran.
                @else
                    Lengkapi alamat, pilih layanan pengiriman tiap toko, dan tentukan metode pembayaran. Hanya produk yang dipilih yang akan diproses.
                @endif
            </p>
        </div>
    </section>

    <section class="page-section">
        <div class="container">
            @if ($shippingError)
                <div class="alert alert-warning app-alert mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>{{ $shippingError }}</div>
                </div>
            @endif

            @if ($midtransReady)
                <div class="alert alert-info app-alert mb-3" role="status" style="background:#eef4fb;border-color:#dbe7f5;color:#255b9b;">
                    <i class="bi bi-credit-card-2-front-fill"></i>
                    <div>
                        Pembayaran online Midtrans aktif pada mode
                        <strong>{{ $midtransAvailability['environment'] === 'production' ? 'production' : 'Sandbox' }}</strong>.
                        Satu transaksi akan mencakup seluruh toko, lalu dana dialokasikan per pesanan melalui
                        <strong>{{ $midtransAvailability['route'] === 'split' ? 'transaction split' : 'disbursement API' }}</strong>.
                    </div>
                </div>
            @elseif ($midtransCheckoutEnabled)
                <div class="alert alert-warning app-alert mb-3" role="alert">
                    <i class="bi bi-shield-exclamation"></i>
                    <div>
                        <strong>Midtrans belum siap digunakan.</strong>
                        {{ $midtransAvailability['reason'] ?? 'Prasyarat Midtrans belum lengkap.' }}
                        Sementara ini gunakan Transfer Bank, QRIS simulasi, atau COD.
                    </div>
                </div>
            @else
                <div class="alert alert-info app-alert mb-3" role="status" style="background:#eef4fb;border-color:#dbe7f5;color:#255b9b;">
                    <i class="bi bi-wallet2"></i>
                    <div>
                        <strong>Pembayaran manual (simulasi) aktif.</strong>
                        Transfer Bank dan QRIS diverifikasi penjual melalui bukti pembayaran. COD dibayar saat pesanan diterima.
                        Pembayaran online Midtrans belum diaktifkan pada demo ini.
                    </div>
                </div>
            @endif

            <form id="checkout-form" action="{{ route('checkout.store') }}" method="POST" data-shipping-url="{{ route('shipping.cost') }}" data-subtotal="{{ $subtotal }}">
                @csrf
                <input type="hidden" name="checkout_token" value="{{ old('checkout_token', $checkoutToken) }}">

                @if ($isDirectCheckout)
                    <input type="hidden" name="product_id" value="{{ $selection['product_id'] }}">
                    <input type="hidden" name="quantity" value="{{ $selection['quantity'] }}">
                @else
                    @foreach ($selection['cart_item_ids'] as $selectedCartItemId)
                        <input type="hidden" name="items[]" value="{{ $selectedCartItemId }}">
                    @endforeach
                @endif

                <div class="checkout-grid">
                    <div>
                        <section class="checkout-section">
                            <div class="checkout-section-head">
                                <span class="checkout-step">1</span>
                                <div>
                                    <h2>Alamat pengiriman</h2>
                                    <p>Alamat ini digunakan untuk seluruh pesanan pada checkout ini.</p>
                                </div>
                            </div>

                            <div class="address-grid">
                                <div>
                                    <label for="recipient_name" class="form-label">Nama penerima</label>
                                    <input type="text" id="recipient_name" name="recipient_name" value="{{ old('recipient_name', $user->name) }}" class="form-control @error('recipient_name') is-invalid @enderror" placeholder="Nama lengkap penerima" required autocomplete="name">
                                    @error('recipient_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div>
                                    <label for="phone" class="form-label">Nomor WhatsApp / telepon</label>
                                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror" placeholder="08xxxxxxxxxx" required autocomplete="tel">
                                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="full">
                                    <label for="address" class="form-label">Alamat lengkap</label>
                                    <textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror" placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, kecamatan" required autocomplete="street-address">{{ old('address', $user->address) }}</textarea>
                                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div>
                                    <label for="city-select" class="form-label">Kota / kabupaten tujuan</label>
                                    <select id="city-select" name="city_id" class="form-select @error('city_id') is-invalid @enderror" required>
                                        <option value="">Pilih kota atau kabupaten</option>
                                        @foreach ($cities->groupBy('province') as $province => $provinceCities)
                                            <optgroup label="{{ $province }}">
                                                @foreach ($provinceCities as $city)
                                                    <option value="{{ $city['id'] }}" @selected($selectedCity === (string) $city['id'])>
                                                        {{ $city['city_name'] }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                    @error('city_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">Ongkir dihitung dari kota asal masing-masing toko.</div>
                                </div>
                                <div>
                                    <label for="postal_code" class="form-label">Kode pos <span class="text-muted fw-normal">(opsional)</span></label>
                                    <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code') }}" class="form-control @error('postal_code') is-invalid @enderror" placeholder="Contoh: 20228" inputmode="numeric" maxlength="10" autocomplete="postal-code">
                                    @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="full">
                                    <label for="notes" class="form-label">Catatan untuk penjual <span class="text-muted fw-normal">(opsional)</span></label>
                                    <textarea id="notes" name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror" placeholder="Contoh: Tolong dibungkus rapi, atau patokan alamat">{{ old('notes') }}</textarea>
                                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </section>

                        <section class="checkout-section">
                            <div class="checkout-section-head">
                                <span class="checkout-step">2</span>
                                <div>
                                    <h2>Pengiriman per toko</h2>
                                    <p>Pesanan dipisah per toko. Pilih layanan kurir untuk masing-masing toko.</p>
                                </div>
                            </div>

                            @foreach ($groups as $storeId => $group)
                                @php($store = $group['store'])
                                <div class="checkout-store" data-checkout-store data-store-id="{{ $store->id }}" data-store-name="{{ $store->name }}" data-weight="{{ $group['weight'] }}">
                                    <div class="checkout-store-head">
                                        <i class="bi bi-shop"></i>
                                        {{ $store->name }}
                                        <span>Dikirim dari {{ $store->city_name }} · {{ berat($group['weight']) }}</span>
                                    </div>

                                    @foreach ($group['items'] as $item)
                                        <div class="checkout-item">
                                            <img src="{{ $item->product->image_url }}" alt="{{ $item->product->name }}">
                                            <div>
                                                <div class="checkout-item-name">{{ $item->product->name }}</div>
                                                <div class="checkout-item-qty">{{ $item->quantity }} × {{ rupiah($item->product->price) }} · {{ berat($item->product->weight) }}</div>
                                            </div>
                                            <span class="checkout-item-total">{{ rupiah($item->product->price * $item->quantity) }}</span>
                                        </div>
                                    @endforeach

                                    <div class="store-shipping">
                                        <label for="shipping-{{ $store->id }}" class="form-label">Layanan pengiriman dari {{ $store->city_name }}</label>
                                        <select id="shipping-{{ $store->id }}" name="shipping[{{ $store->id }}]" class="form-select form-select-sm shipping-select" required disabled data-selected="{{ old('shipping.'.$store->id) }}">
                                            <option value="">Pilih kota tujuan terlebih dahulu</option>
                                        </select>
                                        <div class="shipping-status">Pilih kota tujuan untuk menghitung ongkir.</div>
                                        @error('shipping.'.$store->id)
                                            <div class="text-danger mt-1" style="font-size:.7rem;">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            @endforeach

                            @error('shipping')
                                <div class="alert alert-danger py-2 px-3 mt-2 mb-0" style="font-size:.75rem;">{{ $message }}</div>
                            @enderror
                        </section>

                        <section class="checkout-section">
                            <div class="checkout-section-head">
                                <span class="checkout-step">3</span>
                                <div>
                                    <h2>Metode pembayaran</h2>
                                    <p>Pilih cara pembayaran yang paling nyaman. Instruksi lengkap muncul setelah pesanan dibuat.</p>
                                </div>
                            </div>

                            <div class="payment-options">
                                @foreach ($paymentMethods as $method)
                                    <div class="payment-option">
                                        <input type="radio" id="payment-{{ $method->value }}" name="payment_method" value="{{ $method->value }}" @checked($selectedPayment === $method->value) required>
                                        <label for="payment-{{ $method->value }}">
                                            <i class="bi {{ $method->icon() }}"></i>
                                            <strong>{{ $method->label() }}</strong>
                                            <span>{{ $paymentDescriptions[$method->value] ?? '' }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('payment_method')<div class="text-danger mt-2" style="font-size:.72rem;">{{ $message }}</div>@enderror

                            <div class="payment-hint">
                                <i class="bi bi-info-circle"></i>
                                <span>
                                    @if ($midtransReady)
                                        Midtrans memverifikasi pembayaran secara otomatis. Transfer/QRIS simulasi diverifikasi penjual melalui bukti bayar.
                                    @else
                                        Transfer/QRIS merupakan simulasi: unggah bukti pembayaran pada detail pesanan agar penjual dapat memverifikasi.
                                    @endif
                                    Pesanan COD dikonfirmasi penjual dan dibayar saat diterima.
                                </span>
                            </div>

                            <div class="alert alert-danger d-none mt-3 mb-0" data-shipping-error role="alert"></div>
                        </section>
                    </div>

                    <aside class="summary-panel">
                        <h2 class="text-uppercase" style="font-size:.8rem;letter-spacing:.07em;font-weight:800;">Ringkasan pesanan</h2>

                        <div class="checkout-store-summary">
                            @foreach ($groups as $group)
                                <span>
                                    <span>{{ $group['store']->name }}</span>
                                    <strong>{{ rupiah($group['subtotal']) }}</strong>
                                </span>
                            @endforeach
                        </div>

                        <div class="summary-row">
                            <span>Subtotal produk</span>
                            <strong>{{ rupiah($subtotal) }}</strong>
                        </div>
                        <div class="summary-row">
                            <span>Total berat</span>
                            <strong>{{ berat($totalWeight) }}</strong>
                        </div>
                        <div class="summary-row">
                            <span>Ongkos kirim</span>
                            <strong data-summary-shipping>Rp 0</strong>
                        </div>
                        <div class="summary-row total">
                            <span>Total pembayaran</span>
                            <strong data-summary-total>{{ rupiah($subtotal) }}</strong>
                        </div>

                        <button type="submit" class="btn btn-brand checkout-submit mt-2" data-checkout-submit disabled>
                            <i class="bi bi-shield-lock me-1"></i> Buat pesanan
                        </button>
                        <p class="form-note text-center mb-0">Dengan membuat pesanan, Anda menyetujui ketentuan Sikitaya.</p>

                        <div class="info-note">
                            <i class="bi bi-bag-check"></i>
                            <span>
                                {{ $groups->count() }} pesanan akan dibuat, satu untuk setiap toko.
                                @if ($midtransReady)
                                    Pembayaran Midtrans digabung menjadi satu transaksi; transfer/QRIS simulasi dan COD diselesaikan per pesanan.
                                @else
                                    Transfer/QRIS simulasi diselesaikan per pesanan melalui unggah bukti; COD dibayar terpisah saat barang diterima.
                                @endif
                            </span>
                        </div>

                        <a href="{{ $isDirectCheckout ? route('products.show', $firstProduct) : route('cart.index') }}" class="btn btn-ghost btn-sm w-100 mt-2">
                            <i class="bi bi-arrow-left me-1"></i> {{ $isDirectCheckout ? 'Kembali ke produk' : 'Kembali ke keranjang' }}
                        </a>
                    </aside>
                </div>
            </form>
        </div>
    </section>
@endsection
