<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'store' => ['nullable', 'integer', 'exists:stores,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $orders = Order::query()
            ->with(['store', 'user', 'items', 'paymentTransaction'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['store'] ?? null, fn ($query, $storeId) => $query->where('store_id', $storeId))
            ->when($filters['q'] ?? null, function ($query, $q) {
                $query->where(function ($builder) use ($q) {
                    $builder->where('order_number', 'like', '%'.$q.'%')
                        ->orWhere('recipient_name', 'like', '%'.$q.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$q.'%'));
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $stores = Store::orderBy('name')->get();
        $statusCounts = Order::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.orders.index', compact('orders', 'stores', 'filters', 'statusCounts'));
    }

    public function show(Order $order): View
    {
        $order->load(['store.owner', 'user', 'items.product', 'paymentTransaction.allocations', 'paymentAllocation']);

        return view('admin.orders.show', compact('order'));
    }
}
