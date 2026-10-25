<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly ShippingService $shipping) {}

    /**
     * Membuat satu pesanan per toko dari isi keranjang pembeli.
     *
     * @return Collection<int, Order>
     */
    public function checkout(User $buyer, array $data): Collection
    {
        $cartItems = $buyer->cartItems()->with(['product.store'])->get();

        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Keranjang belanja Anda masih kosong.']);
        }

        $destination = $this->shipping->findCity((int) $data['city_id']);

        if (! $destination) {
            throw ValidationException::withMessages(['city_id' => 'Kota tujuan tidak ditemukan.']);
        }

        $groups = $cartItems->groupBy(fn ($item) => $item->product->store_id);
        $paymentMethod = PaymentMethod::from($data['payment_method']);
        $orders = collect();

        DB::transaction(function () use ($buyer, $data, $groups, $destination, $paymentMethod, &$orders) {
            foreach ($groups as $storeId => $items) {
                $store = $items->first()->product->store;

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

                foreach ($items as $item) {
                    $product = Product::with('store')->whereKey($item->product_id)->lockForUpdate()->first();

                    if (! $product || ! $product->is_active || ! $product->store?->is_active) {
                        throw ValidationException::withMessages(['cart' => 'Produk "'.$item->product->name.'" sudah tidak tersedia.']);
                    }

                    if ($product->stock < $item->quantity) {
                        throw ValidationException::withMessages(['cart' => 'Stok "'.$product->name.'" tersisa '.$product->stock.'.']);
                    }

                    $lockedItems[] = ['product' => $product, 'quantity' => $item->quantity];
                    $weight += $product->weight * $item->quantity;
                    $subtotal += $product->price * $item->quantity;
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

            $buyer->cartItems()->delete();
        });

        return $orders;
    }

    public function confirmPayment(Order $order): void
    {
        if ($order->status !== OrderStatus::Menunggu) {
            throw ValidationException::withMessages(['status' => 'Pesanan ini tidak berada pada status menunggu.']);
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
