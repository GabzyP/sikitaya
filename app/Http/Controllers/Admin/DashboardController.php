<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'sellers' => User::where('role', UserRole::Seller->value)->count(),
            'buyers' => User::where('role', UserRole::Buyer->value)->count(),
            'stores' => Store::count(),
            'active_stores' => Store::where('is_active', true)->count(),
            'products' => Product::count(),
            'orders' => Order::count(),
            'omzet' => Order::whereIn('status', [OrderStatus::Dibayar->value, OrderStatus::Dikirim->value, OrderStatus::Selesai->value])->sum('total'),
            'pending' => Order::where('status', OrderStatus::Menunggu->value)->count(),
        ];

        $recentOrders = Order::with(['store', 'user', 'items'])->latest()->take(6)->get();

        $topStores = Order::query()
            ->select('store_id', DB::raw('SUM(total) as revenue'), DB::raw('COUNT(*) as orders_count'))
            ->whereIn('status', [OrderStatus::Dibayar->value, OrderStatus::Dikirim->value, OrderStatus::Selesai->value])
            ->groupBy('store_id')
            ->orderByDesc('revenue')
            ->with('store')
            ->limit(5)
            ->get();

        $chartStart = now()->subMonths(5)->startOfMonth();
        $chartOrders = Order::where('created_at', '>=', $chartStart)
            ->whereIn('status', [OrderStatus::Dibayar->value, OrderStatus::Dikirim->value, OrderStatus::Selesai->value])
            ->get(['created_at', 'total'])
            ->groupBy(fn (Order $order) => $order->created_at->format('Y-m'));

        $chart = collect(range(5, 0))->map(function (int $monthsAgo) use ($chartOrders) {
            $month = now()->subMonths($monthsAgo);
            $revenue = $chartOrders->get($month->format('Y-m'), collect())->sum('total');

            return [
                'label' => $month->locale('id')->translatedFormat('M Y'),
                'revenue' => (int) $revenue,
            ];
        });

        $statusCounts = Order::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.dashboard', compact('stats', 'recentOrders', 'topStores', 'chart', 'statusCounts'));
    }
}
