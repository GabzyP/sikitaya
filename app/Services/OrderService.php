<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly MidtransPaymentService $payments,
    ) {}

    /**
     * Membuat satu pesanan per toko dan satu transaksi online gabungan.
     *
     * @param  array{mode?: 'cart'|'direct', product_id?: int, quantity?: int, cart_item_ids?: array<int, int>|null}  $selection
     * @return array{orders: Collection<int, Order>, payment: ?PaymentTransaction}
     */
    public function checkout(User $buyer, array $data, string $checkoutToken, array $selection = []): array
    {
        $destination = $this->shipping->findCity((int) $data['city_id']);

        if (! $destination) {
            throw ValidationException::withMessages(['city_id' => 'Kota tujuan tidak ditemukan.']);
        }

        $paymentMethod = PaymentMethod::from($data['payment_method']);

        if ($paymentMethod === PaymentMethod::Midtrans && ! config('midtrans.checkout_enabled', false)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Pembayaran Midtrans disimpan tetapi belum diaktifkan untuk checkout.',
            ]);
        }

        return DB::transaction(function () use ($buyer, $data, $destination, $paymentMethod, $checkoutToken, $selection) {
            $existing = PaymentTransaction::where('checkout_token', $checkoutToken)->first();

            if ($existing) {
                if ($existing->user_id !== $buyer->id) {
                    throw ValidationException::withMessages(['checkout_token' => 'Token checkout tidak valid.']);
                }

                return [
                    'orders' => $existing->orders()->with('store', 'items')->get(),
                    'payment' => $existing,
                ];
            }

            $mode = $selection['mode'] ?? 'cart';
            $selectedLines = collect();

            if ($mode === 'direct') {
                $product = Product::with('store')
                    ->whereKey($selection['product_id'] ?? 0)
                    ->lockForUpdate()
                    ->first();

                if (! $product || ! $product->is_active || ! $product->store?->is_active) {
                    throw ValidationException::withMessages(['cart' => 'Produk yang dipilih sudah tidak tersedia.']);
                }

                if ($product->store->user_id === $buyer->id) {
                    throw ValidationException::withMessages(['cart' => 'Anda tidak dapat membeli produk dari toko Anda sendiri.']);
                }

                $selectedLines->push([
                    'product_id' => $product->id,
                    'quantity' => max(1, (int) ($selection['quantity'] ?? 1)),
                    'cart_item_id' => null,
                    'product' => $product,
                ]);
            } else {
                $selectedIds = $selection['cart_item_ids'] ?? null;
                $cartQuery = $buyer->cartItems()
                    ->with(['product.store'])
                    ->lockForUpdate();

                if (is_array($selectedIds)) {
                    $cartQuery->whereIn('id', $selectedIds);
                }

                $cartItems = $cartQuery->get();

                if ($cartItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' => $selectedIds === null
                            ? 'Keranjang belanja Anda masih kosong.'
                            : 'Tidak ada produk yang dipilih untuk di-checkout.',
                    ]);
                }

                if (is_array($selectedIds) && $cartItems->count() !== count($selectedIds)) {
                    throw ValidationException::withMessages(['cart' => 'Sebagian produk yang dipilih tidak ditemukan di keranjang Anda.']);
                }

                foreach ($cartItems as $item) {
                    $selectedLines->push([
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'cart_item_id' => $item->id,
                        'product' => $item->product,
                    ]);
                }
            }

            $groups = $selectedLines->groupBy(fn ($line) => $line['product']->store_id);
            $orders = collect();

            foreach ($groups as $storeId => $items) {
                $store = $items->first()['product']->store;

                if (! $store || ! $store->is_active) {
                    throw ValidationException::withMessages(['cart' => 'Salah satu toko pada keranjang sudah tidak aktif.']);
                }

                $shippingValue = $data['shipping'][$storeId] ?? null;

                if (! is_string($shippingValue) || ! Str::contains($shippingValue, '|')) {
                    throw ValidationException::withMessages(['shipping.'.$storeId => 'Pilih layanan pengiriman untuk toko '.$store->name.'.']);
                }

                [$courier, $service] = explode('|', $shippingValue, 2);

                $lockedItems = [];
                $weight = 0;
                $subtotal = 0;

                foreach ($items as $line) {
                    $product = Product::with('store')->whereKey($line['product_id'])->lockForUpdate()->first();

                    if (! $product || ! $product->is_active || ! $product->store?->is_active) {
                        throw ValidationException::withMessages(['cart' => 'Produk "'.$line['product']->name.'" sudah tidak tersedia.']);
                    }

                    if ($product->store->user_id === $buyer->id) {
                        throw ValidationException::withMessages(['cart' => 'Anda tidak dapat membeli produk dari toko Anda sendiri.']);
                    }

                    if ($product->stock < $line['quantity']) {
                        throw ValidationException::withMessages(['cart' => 'Stok "'.$product->name.'" tersisa '.$product->stock.'.']);
                    }

                    $lockedItems[] = ['product' => $product, 'quantity' => $line['quantity']];
                    $weight += $product->weight * $line['quantity'];
                    $subtotal += $product->price * $line['quantity'];
                }

                $options = $this->shipping->cost($store->city_id, $destination['id'], $weight, $courier);
                $selectedShipping = collect($options)->firstWhere('service', $service);

                if (! $selectedShipping) {
                    throw ValidationException::withMessages(['shipping.'.$storeId => 'Layanan pengiriman yang dipilih tidak lagi tersedia. Silakan hitung ulang ongkir.']);
                }

                $order = Order::create([
                    'order_number' => $this->nextOrderNumber(),
                    'user_id' => $buyer->id,
                    'store_id' => $store->id,
                    'recipient_name' => $data['recipient_name'],
                    'phone' => $data['phone'],
                    'address' => $data['address'],
                    'city_id' => $destination['id'],
                    'city_name' => $destination['city_name'],
                    'province_name' => $destination['province'],
                    'postal_code' => $data['postal_code'] ?? null,
                    'courier' => $courier,
                    'shipping_service' => $selectedShipping['service'],
                    'shipping_etd' => $selectedShipping['etd'],
                    'shipping_cost' => $selectedShipping['cost'],
                    'subtotal' => $subtotal,
                    'total' => $subtotal + $selectedShipping['cost'],
                    'payment_method' => $paymentMethod->value,
                    'status' => OrderStatus::Menunggu->value,
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($lockedItems as $locked) {
                    $product = $locked['product'];
                    $quantity = $locked['quantity'];

                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_image' => $product->image,
                        'product_price' => $product->price,
                        'quantity' => $quantity,
                        'subtotal' => $product->price * $quantity,
                        'weight' => $product->weight,
                    ]);

                    $product->decrement('stock', $quantity);
                }

                $orders->push($order->load('store', 'items'));
            }

            $payment = null;

            if ($paymentMethod === PaymentMethod::Midtrans) {
                $payment = $this->payments->createForOrders($buyer, $orders, $checkoutToken);
            }

            $selectedCartItemIds = $selectedLines
                ->pluck('cart_item_id')
                ->filter()
                ->values();

            if ($selectedCartItemIds->isNotEmpty()) {
                $buyer->cartItems()->whereIn('id', $selectedCartItemIds)->delete();
            }

            return [
                'orders' => $orders,
                'payment' => $payment,
            ];
        });
    }

    public function confirmPayment(Order $order): void
    {
        if ($order->status !== OrderStatus::Menunggu) {
            throw ValidationException::withMessages(['status' => 'Pesanan ini tidak berada pada status menunggu.']);
        }

        if ($order->payment_method === PaymentMethod::Midtrans) {
            $payment = $order->paymentTransaction;

            if (! $payment?->isPaid()) {
                throw ValidationException::withMessages([
                    'status' => 'Pembayaran Midtrans belum dikonfirmasi sebagai lunas oleh gateway.',
                ]);
            }
        }

        if ($order->requiresPaymentProof() && ! $order->payment_proof) {
            throw ValidationException::withMessages(['status' => 'Pembeli belum mengunggah bukti pembayaran.']);
        }

        $order->update([
            'status' => OrderStatus::Dibayar,
            'paid_at' => $order->payment_method === PaymentMethod::Cod ? null : now(),
        ]);
    }

    public function ship(Order $order, string $trackingNumber): void
    {
        if ($order->status !== OrderStatus::Dibayar) {
            throw ValidationException::withMessages(['status' => 'Pesanan harus berstatus dibayar sebelum dikirim.']);
        }

        $order->update([
            'status' => OrderStatus::Dikirim,
            'shipped_at' => now(),
            'tracking_number' => $trackingNumber,
        ]);
    }

    public function complete(Order $order): void
    {
        if ($order->status !== OrderStatus::Dikirim) {
            throw ValidationException::withMessages(['status' => 'Pesanan hanya dapat diselesaikan setelah dikirim.']);
        }

        $order->update([
            'status' => OrderStatus::Selesai,
            'completed_at' => now(),
            'paid_at' => $order->payment_method === PaymentMethod::Cod ? ($order->paid_at ?? now()) : $order->paid_at,
        ]);
    }

    private function nextOrderNumber(): string
    {
        do {
            $number = 'SKT-'.now()->format('ymd').'-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
