<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $store = auth()->user()->store;

        $orders = Order::forStore($store->id)
            ->with(['user', 'items', 'paymentTransaction'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, $q) {
                $query->where(function ($builder) use ($q) {
                    $builder->where('order_number', 'like', '%'.$q.'%')
                        ->orWhere('recipient_name', 'like', '%'.$q.'%');
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $statusCounts = Order::forStore($store->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('seller.orders.index', compact('orders', 'filters', 'statusCounts', 'store'));
    }

    public function show(Order $order): View
    {
        $this->authorizeOrder($order);

        $order->load(['user', 'items.product', 'paymentTransaction', 'paymentAllocation']);

        return view('seller.orders.show', compact('order'));
    }

    public function confirmPayment(Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeOrder($order);

        $orders->confirmPayment($order);

        return back()->with('success', 'Pembayaran pesanan '.$order->order_number.' berhasil dikonfirmasi.');
    }

    public function ship(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeOrder($order);

        $data = $request->validate([
            'tracking_number' => ['required', 'string', 'max:60'],
        ], [
            'tracking_number.required' => 'Nomor resi wajib diisi sebelum menandai pesanan dikirim.',
        ]);

        $orders->ship($order, $data['tracking_number']);

        return back()->with('success', 'Pesanan '.$order->order_number.' ditandai sebagai dikirim.');
    }

    private function authorizeOrder(Order $order): void
    {
        abort_unless($order->store_id === auth()->user()->store->id, 403);
    }
}
