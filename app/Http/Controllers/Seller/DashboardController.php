<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $store = auth()->user()->store;
        $monthStart = now()->startOfMonth();

        $baseQuery = Order::forStore($store->id);

        $stats = [
            'omzet_bulan_ini' => (clone $baseQuery)
                ->where('created_at', '>=', $monthStart)
                ->whereIn('status', [OrderStatus::Dibayar->value, OrderStatus::Dikirim->value, OrderStatus::Selesai->value])
                ->sum('total'),
            'pesanan_bulan_ini' => (clone $baseQuery)->where('created_at', '>=', $monthStart)->count(),
            'perlu_diproses' => (clone $baseQuery)->whereIn('status', [OrderStatus::Menunggu->value, OrderStatus::Dibayar->value])->count(),
            'produk_aktif' => Product::where('store_id', $store->id)->where('is_active', true)->count(),
            'stok_rendah' => Product::where('store_id', $store->id)->where('is_active', true)->where('stock', '<=', 5)->count(),
            'menunggu_verifikasi' => (clone $baseQuery)->where('status', OrderStatus::Menunggu->value)->whereNotNull('payment_proof')->count(),
        ];

        $recentOrders = (clone $baseQuery)->with(['user', 'items'])->latest()->take(5)->get();

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.store_id', $store->id)
            ->whereIn('orders.status', [OrderStatus::Dibayar->value, OrderStatus::Dikirim->value, OrderStatus::Selesai->value])
            ->select('order_items.product_name', DB::raw('SUM(order_items.quantity) as total_qty'), DB::raw('SUM(order_items.subtotal) as total_amount'))
            ->groupBy('order_items.product_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $chartStart = now()->subMonths(5)->startOfMonth();
        $chartOrders = (clone $baseQuery)
            ->where('created_at', '>=', $chartStart)
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

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('seller.dashboard', compact('store', 'stats', 'recentOrders', 'topProducts', 'chart', 'statusCounts'));
    }
}
