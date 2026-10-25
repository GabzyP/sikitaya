<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Services\OrderService;
use App\Services\ShippingException;
use App\Services\ShippingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly OrderService $orders,
    ) {}

    public function index(): View|RedirectResponse
    {
        $items = auth()->user()->cartItems()
            ->with(['product.store', 'product.category'])
            ->get();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang Anda masih kosong.');
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

        return view('checkout.index', compact('groups', 'subtotal', 'totalWeight', 'cities', 'shippingError', 'user'))
            ->with('paymentMethods', PaymentMethod::cases());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\-\s]{8,20}$/'],
            'address' => ['required', 'string', 'max:500'],
            'city_id' => ['required', 'integer'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'in:transfer,qris,cod'],
            'shipping' => ['required', 'array'],
            'shipping.*' => ['required', 'string', 'regex:/^(jne|pos|tiki)\|[A-Za-z0-9 ]+$/'],
        ]);

        try {
            $orders = $this->orders->checkout($request->user(), $data);
        } catch (ShippingException $exception) {
            return back()->withInput()->withErrors(['shipping' => $exception->getMessage()]);
        }

        $numbers = $orders->pluck('order_number')->implode(', ');
        $storeCount = $orders->count();

        return redirect()->route('orders.index')
            ->with('success', $storeCount.' pesanan berhasil dibuat ('.$numbers.'). Silakan lanjutkan pembayaran sesuai metode yang dipilih.');
    }
}
