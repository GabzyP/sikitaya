<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ]);

        $orders = Order::forBuyer(auth()->id())
            ->with(['store', 'items', 'paymentTransaction'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $counts = Order::forBuyer(auth()->id())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('orders.index', compact('orders', 'filters', 'counts'));
    }

    public function show(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $order->load(['store', 'items.product', 'user', 'paymentTransaction', 'paymentAllocation']);

        return view('orders.show', compact('order'));
    }

    public function uploadProof(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === auth()->id(), 403);
        abort_unless($order->status === OrderStatus::Menunggu, 403, 'Bukti bayar tidak dapat diunggah pada status ini.');
        abort_unless($order->requiresPaymentProof(), 403, 'Metode pembayaran ini tidak memerlukan bukti bayar.');

        $request->validate([
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:2048'],
        ], [
            'payment_proof.mimes' => 'Bukti bayar harus berupa JPG, PNG, WEBP, atau PDF.',
            'payment_proof.max' => 'Ukuran bukti bayar maksimal 2 MB.',
        ]);

        if ($order->payment_proof) {
            Storage::disk('public')->delete($order->payment_proof);
        }

        $path = $request->file('payment_proof')->store('payment-proofs', 'public');

        $order->update([
            'payment_proof' => $path,
            'payment_proof_uploaded_at' => now(),
        ]);

        return back()->with('success', 'Bukti pembayaran berhasil diunggah. Penjual akan memverifikasi pembayaran Anda.');
    }

    public function complete(Order $order, OrderService $orders): RedirectResponse
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $orders->complete($order);

        return back()->with('success', 'Terima kasih! Pesanan '.$order->order_number.' telah selesai.');
    }
}
