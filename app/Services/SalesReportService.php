<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SalesReportService
{
    /**
     * @return array{from:Carbon,to:Carbon,status:?OrderStatus,store_id:?int}
     */
    public function filters(array $input, ?int $forcedStoreId = null): array
    {
        $from = filled($input['from'] ?? null)
            ? Carbon::parse($input['from'])->startOfDay()
            : now()->startOfMonth();

        $to = filled($input['to'] ?? null)
            ? Carbon::parse($input['to'])->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            throw ValidationException::withMessages(['from' => 'Tanggal awal tidak boleh setelah tanggal akhir.']);
        }

        $status = null;

        if (filled($input['status'] ?? null)) {
            $status = OrderStatus::tryFrom($input['status']);

            if (! $status) {
                throw ValidationException::withMessages(['status' => 'Status pesanan tidak valid.']);
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'store_id' => $forcedStoreId ?? (filled($input['store_id'] ?? null) ? (int) $input['store_id'] : null),
        ];
    }

    public function query(array $filters, ?int $storeId = null): Builder
    {
        $storeId ??= $filters['store_id'] ?? null;

        return Order::query()
            ->with(['store', 'user', 'items'])
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->when($filters['status'], fn (Builder $query, OrderStatus $status) => $query->where('status', $status->value))
            ->latest();
    }

    /**
     * @return array{orders:int,items:int,omzet:int,pending:int}
     */
    public function summary(array $filters, ?int $storeId = null): array
    {
        $base = $this->query($filters, $storeId)->reorder()->without(['store', 'user', 'items']);
        $orders = (clone $base)->count();
        $items = (int) (clone $base)->withSum('items', 'quantity')->get()->sum('items_sum_quantity');
        $omzet = (int) (clone $base)
            ->whereIn('status', [OrderStatus::Dibayar->value, OrderStatus::Dikirim->value, OrderStatus::Selesai->value])
            ->sum('total');
        $pending = (clone $base)->where('status', OrderStatus::Menunggu->value)->count();

        return [
            'orders' => $orders,
            'items' => $items,
            'omzet' => $omzet,
            'pending' => $pending,
        ];
    }

    /**
     * @return Collection<int, array<int, string|int>>
     */
    public function exportRows(Collection $orders): Collection
    {
        return $orders->values()->map(fn (Order $order, int $index) => [
            $index + 1,
            $order->order_number,
            $order->created_at->format('d/m/Y H:i'),
            $order->store->name,
            $order->user->name,
            $order->status->label(),
            $order->payment_method->shortLabel(),
            $order->items->sum('quantity'),
            $order->subtotal,
            $order->shipping_cost,
            $order->total,
        ]);
    }
}
