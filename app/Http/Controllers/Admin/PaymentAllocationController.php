<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\MidtransGateway;
use App\Enums\PaymentAllocationMethod;
use App\Enums\PaymentAllocationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentAllocation;
use App\Models\PaymentTransaction;
use App\Models\Store;
use App\Services\MidtransPaymentService;
use App\Services\PaymentAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PaymentAllocationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PaymentTransactionStatus::class)],
            'method' => ['nullable', Rule::enum(PaymentAllocationMethod::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $transactions = PaymentTransaction::query()
            ->with('user')
            ->withCount(['orders', 'allocations'])
            ->withSum('allocations', 'net_amount')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['method'] ?? null, fn ($query, $method) => $query->where('allocation_method', $method))
            ->when($filters['q'] ?? null, function ($query, $q) {
                $query->where(function ($builder) use ($q) {
                    $builder->where('reference', 'like', '%'.$q.'%')
                        ->orWhere('midtrans_order_id', 'like', '%'.$q.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$q.'%'))
                        ->orWhereHas('orders', fn ($order) => $order->where('order_number', 'like', '%'.$q.'%'));
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $statusCounts = PaymentTransaction::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stores = Store::query()
            ->with('owner')
            ->orderBy('name')
            ->get();

        $storeRows = $stores->map(fn (Store $store) => [
            'store' => $store,
            'split_ready' => $store->isReadyForSplit(),
            'payout_ready' => $store->hasVerifiedPayoutAccount(),
        ]);

        $gateway = app(MidtransGateway::class);
        $integration = [
            'configured' => $gateway->isConfigured(),
            'configuration_error' => $gateway->configurationError(),
            'production' => $gateway->isProduction(),
            'split_enabled' => $gateway->splitEnabled(),
            'disbursement_enabled' => $gateway->disbursementEnabled(),
            'platform_merchant_ready' => filled(config('midtrans.platform_merchant_id')),
            'split_ready_count' => $storeRows->where('split_ready', true)->count(),
            'payout_ready_count' => $storeRows->where('payout_ready', true)->count(),
        ];

        return view('admin.payments.index', compact('transactions', 'filters', 'statusCounts', 'storeRows', 'integration'));
    }

    public function show(PaymentTransaction $paymentTransaction): View
    {
        $paymentTransaction->load([
            'user',
            'orders.store.owner',
            'orders.items',
            'allocations.store',
            'allocations.seller',
            'allocations.order',
            'allocations.events.actor',
            'attempts' => fn ($query) => $query->latest('attempt_number'),
            'webhookLogs' => fn ($query) => $query->latest()->limit(15),
        ]);

        return view('admin.payments.show', compact('paymentTransaction'));
    }

    public function sync(PaymentTransaction $paymentTransaction, MidtransPaymentService $payments): RedirectResponse
    {
        try {
            $result = $payments->syncStatus($paymentTransaction);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Status Midtrans belum dapat disinkronkan: '.$this->message($exception));
        }

        return back()->with(
            $result['outcome'] === 'paid' ? 'success' : 'warning',
            $result['message'] ?? 'Status pembayaran diperbarui.'
        );
    }

    public function approve(PaymentAllocation $allocation, PaymentAllocationService $allocations): RedirectResponse
    {
        try {
            $allocations->approve($allocation, auth()->user());
        } catch (Throwable $exception) {
            return back()->with('error', $this->message($exception));
        }

        return back()->with('success', 'Alokasi untuk pesanan '.$allocation->order->order_number.' berhasil disetujui.');
    }

    public function send(PaymentAllocation $allocation, PaymentAllocationService $allocations): RedirectResponse
    {
        try {
            $result = $allocations->send($allocation, auth()->user());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Pencairan belum berhasil: '.$this->message($exception));
        }

        return back()->with(
            ($result['status'] ?? null) === PaymentAllocationStatus::Failed->value ? 'warning' : 'success',
            'Permintaan pencairan dikirim ke Midtrans dengan referensi '.($result['reference'] ?? '-').'. Status saat ini: '.($result['status'] ?? 'processing').'.'
        );
    }

    public function syncAllocation(PaymentAllocation $allocation, PaymentAllocationService $allocations): RedirectResponse
    {
        try {
            $result = $allocations->sync($allocation);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Status pencairan belum dapat diperbarui: '.$this->message($exception));
        }

        return back()->with(
            $result['status'] === PaymentAllocationStatus::Completed->value ? 'success' : 'warning',
            'Status pencairan diperbarui menjadi '.($result['status'] ?? '-').' (gateway: '.($result['gateway_status'] ?? '-').').'
        );
    }

    private function message(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return $exception->validator->errors()->first();
        }

        return $exception->getMessage();
    }
}
