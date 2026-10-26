<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\MidtransPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PaymentController extends Controller
{
    public function show(PaymentTransaction $paymentTransaction): View
    {
        $this->authorizeTransaction($paymentTransaction);

        $paymentTransaction->load([
            'user',
            'orders.store',
            'allocations.store',
            'attempts' => fn ($query) => $query->latest('attempt_number'),
        ]);

        return view('payments.show', compact('paymentTransaction'));
    }

    public function retry(PaymentTransaction $paymentTransaction, MidtransPaymentService $payments): RedirectResponse
    {
        $this->authorizeTransaction($paymentTransaction);

        try {
            $attempt = $payments->retryPayment($paymentTransaction);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception instanceof ValidationException
                ? $exception->validator->errors()->first()
                : 'Pembayaran ulang gagal disiapkan. Silakan coba lagi.');
        }

        return filled($attempt->snap_redirect_url)
            ? back()->with('success', 'Tautan pembayaran baru berhasil dibuat. Lanjutkan pembayaran melalui Midtrans.')
            : back()->with('error', 'Tautan pembayaran Midtrans belum berhasil dibuat. Silakan coba lagi.');
    }

    public function sync(PaymentTransaction $paymentTransaction, MidtransPaymentService $payments): RedirectResponse
    {
        $this->authorizeTransaction($paymentTransaction);

        try {
            $result = $payments->syncStatus($paymentTransaction);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception instanceof ValidationException
                ? $exception->validator->errors()->first()
                : 'Status Midtrans belum dapat diperbarui. Silakan coba lagi nanti.');
        }

        return back()->with(
            $result['outcome'] === 'paid' ? 'success' : 'warning',
            $result['message'] ?? 'Status pembayaran diperbarui.'
        );
    }

    private function authorizeTransaction(PaymentTransaction $transaction): void
    {
        abort_unless($transaction->user_id === auth()->id(), 403);
    }
}
