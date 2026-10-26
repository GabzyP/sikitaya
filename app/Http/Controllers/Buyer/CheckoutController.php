<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\MidtransPaymentService;
use App\Services\OrderService;
use App\Services\ShippingException;
use App\Services\ShippingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly OrderService $orders,
        private readonly MidtransPaymentService $payments,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->has('product_id')) {
            $data = $request->validate([
                'product_id' => ['required', 'integer', 'min:1'],
                'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            ]);

            $quantity = (int) ($data['quantity'] ?? 1);
            $product = Product::active()
                ->with(['store', 'category'])
                ->find($data['product_id']);

            if (! $product) {
                return redirect()->route('products.index')->with('error', 'Produk tidak tersedia.');
            }

            if ($product->store->user_id === auth()->id()) {
                return redirect()->route('products.show', $product)
                    ->with('error', 'Anda tidak dapat membeli produk dari toko Anda sendiri.');
            }

            if ($product->stock < $quantity) {
                return redirect()->route('products.show', $product)
                    ->with('error', 'Jumlah melebihi stok tersedia ('.$product->stock.' item).');
            }

            $line = new CartItem([
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);
            $line->setRelation('product', $product);
            $items = collect([$line]);
            $selection = [
                'mode' => 'direct',
                'product_id' => $product->id,
                'quantity' => $quantity,
                'cart_item_ids' => [],
            ];
        } else {
            $selectedIds = $this->selectedCartItemIds($request);

            if ($request->boolean('cart_selection') && $selectedIds === null) {
                return redirect()->route('cart.index')
                    ->with('error', 'Pilih minimal satu produk yang ingin di-checkout.');
            }

            if ($selectedIds === []) {
                return redirect()->route('cart.index')
                    ->with('error', 'Produk yang dipilih tidak valid. Silakan pilih ulang.');
            }

            $query = auth()->user()->cartItems()
                ->with(['product.store', 'product.category']);

            if ($selectedIds !== null) {
                $query->whereIn('id', $selectedIds);
            }

            $items = $query->get();

            if ($selectedIds !== null && $items->count() !== count($selectedIds)) {
                return redirect()->route('cart.index')
                    ->with('error', 'Sebagian produk yang dipilih tidak ditemukan di keranjang Anda.');
            }

            if ($items->isEmpty()) {
                return redirect()->route('cart.index')->with('error', 'Keranjang Anda masih kosong.');
            }

            $selection = [
                'mode' => 'cart',
                'cart_item_ids' => $items->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ];
        }

        $shippingError = null;
        $cities = collect();

        try {
            $cities = $this->shipping->cities();
        } catch (ShippingException $exception) {
            $shippingError = $exception->getMessage();
        }

        $groups = $items->groupBy(fn (CartItem $item) => $item->product->store_id)
            ->map(function (Collection $storeItems) {
                $store = $storeItems->first()->product->store;

                return [
                    'store' => $store,
                    'items' => $storeItems,
                    'subtotal' => $storeItems->sum(fn (CartItem $item) => $item->product->price * $item->quantity),
                    'weight' => $storeItems->sum(fn (CartItem $item) => $item->product->weight * $item->quantity),
                ];
            });

        $subtotal = $groups->sum('subtotal');
        $totalWeight = $groups->sum('weight');
        $user = auth()->user();
        $midtransAvailability = $this->payments->availability($groups->pluck('store'));
        $midtransCheckoutEnabled = (bool) config('midtrans.checkout_enabled', false);
        $midtransReady = $midtransCheckoutEnabled && $midtransAvailability['available'];
        $paymentMethods = PaymentMethod::checkoutOptions($midtransReady);

        $checkoutToken = (string) Str::uuid();
        session(['checkout_token' => $checkoutToken]);

        return view('checkout.index', compact(
            'groups',
            'subtotal',
            'totalWeight',
            'cities',
            'shippingError',
            'user',
            'paymentMethods',
            'midtransAvailability',
            'midtransCheckoutEnabled',
            'midtransReady',
            'checkoutToken',
            'selection',
        ));
    }

    public function direct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::active()->with('store')->find($data['product_id']);

        if (! $product) {
            return back()->with('error', 'Produk tidak tersedia.');
        }

        if ($product->store->user_id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat membeli produk dari toko Anda sendiri.');
        }

        if ($product->stock < $data['quantity']) {
            return back()->with('error', 'Jumlah melebihi stok tersedia ('.$product->stock.' item).');
        }

        return redirect()->route('checkout.index', [
            'product_id' => $product->id,
            'quantity' => $data['quantity'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $paymentMethods = PaymentMethod::checkoutOptions((bool) config('midtrans.checkout_enabled', false));

        $data = $request->validate([
            'checkout_token' => ['required', 'string', 'size:36'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\-\s]{8,20}$/'],
            'address' => ['required', 'string', 'max:500'],
            'city_id' => ['required', 'integer'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::in(array_map(fn (PaymentMethod $method) => $method->value, $paymentMethods))],
            'shipping' => ['required', 'array'],
            'shipping.*' => ['required', 'string', 'regex:/^(jne|pos|tiki)\|[A-Za-z0-9 ]+$/'],
        ]);

        $paymentMethod = PaymentMethod::from($data['payment_method']);

        if (! hash_equals((string) $request->session()->get('checkout_token', ''), $data['checkout_token'])) {
            throw ValidationException::withMessages([
                'checkout_token' => 'Sesi checkout tidak valid. Muat ulang halaman checkout.',
            ]);
        }

        $selection = $this->selectionPayload($request);

        try {
            $result = $this->orders->checkout($request->user(), $data, $data['checkout_token'], $selection);
        } catch (ShippingException $exception) {
            return back()->withInput()->withErrors(['shipping' => $exception->getMessage()]);
        }

        $request->session()->forget('checkout_token');

        $orders = $result['orders'];
        $payment = $result['payment'];

        if (! $payment) {
            $numbers = $orders->pluck('order_number')->implode(', ');

            $message = $paymentMethod === PaymentMethod::Cod
                ? $orders->count().' pesanan COD berhasil dibuat ('.$numbers.'). Silakan lanjutkan sesuai alur COD.'
                : $orders->count().' pesanan berhasil dibuat ('.$numbers.'). Silakan lakukan pembayaran simulasi Transfer/QRIS, lalu unggah bukti pada masing-masing detail pesanan.';

            return redirect()->route('orders.index')->with('success', $message);
        }

        if (! $payment->isPending()) {
            return redirect()->route('payments.show', $payment)
                ->with('warning', 'Transaksi ini sudah diproses sebelumnya. Status terbaru ditampilkan di halaman pembayaran.');
        }

        try {
            $attempt = $this->payments->startPayment($payment);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('payments.show', $payment)
                ->with('error', 'Pesanan dibuat, tetapi tautan pembayaran Midtrans gagal disiapkan. Silakan coba lagi dari halaman pembayaran.');
        }

        if (filled($attempt->snap_redirect_url)) {
            return redirect()->route('payments.show', $payment)
                ->with('success', 'Pesanan gabungan berhasil dibuat. Lanjutkan pembayaran melalui Midtrans.');
        }

        return redirect()->route('payments.show', $payment)
            ->with('error', 'Pesanan dibuat, tetapi Snap Midtrans belum berhasil dibuat. Periksa pesan di halaman pembayaran lalu coba lagi.');
    }

    /**
     * @return array{mode: 'direct', product_id: int, quantity: int}|array{mode: 'cart', cart_item_ids: array<int, int>|null}
     */
    private function selectionPayload(Request $request): array
    {
        if ($request->has('product_id')) {
            $data = $request->validate([
                'product_id' => ['required', 'integer', 'min:1'],
                'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            ]);

            return [
                'mode' => 'direct',
                'product_id' => (int) $data['product_id'],
                'quantity' => (int) $data['quantity'],
            ];
        }

        $data = $request->validate([
            'items' => ['sometimes', 'array'],
            'items.*' => ['integer', 'min:1'],
        ]);

        if (array_key_exists('items', $data)) {
            return [
                'mode' => 'cart',
                'cart_item_ids' => collect($data['items'])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all(),
            ];
        }

        return ['mode' => 'cart', 'cart_item_ids' => null];
    }

    /**
     * @return array<int, int>|null Null means all items in the cart.
     */
    private function selectedCartItemIds(Request $request): ?array
    {
        if (! $request->has('items')) {
            return null;
        }

        $items = $request->input('items');

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
