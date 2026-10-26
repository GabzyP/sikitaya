<?php

namespace App\Console\Commands;

use App\Contracts\MidtransGateway;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Services\MidtransPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpirePendingPayments extends Command
{
    protected $signature = 'payments:expire';

    protected $description = 'Sinkronkan atau tutup transaksi Midtrans pending yang melewati batas waktu';

    public function handle(MidtransPaymentService $payments, MidtransGateway $gateway): int
    {
        $closed = 0;
        $checked = 0;

        PaymentTransaction::query()
            ->where('status', PaymentTransactionStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(50, function ($transactions) use ($payments, $gateway, &$closed, &$checked) {
                foreach ($transactions as $transaction) {
                    $checked++;
                    $attempt = $transaction->attempts()->latest('attempt_number')->first();

                    if (! $gateway->isConfigured() || ! $attempt || $attempt->status !== 'created') {
                        $payments->closeWithoutGateway(
                            $transaction,
                            PaymentTransactionStatus::Expired,
                            'Batas waktu pembayaran berakhir sebelum tautan Midtrans berhasil dibuat.'
                        );
                        $closed++;

                        continue;
                    }

                    try {
                        $payments->syncStatus($transaction);
                    } catch (Throwable $exception) {
                        Log::warning('Gagal memeriksa transaksi Midtrans kedaluwarsa.', [
                            'reference' => $transaction->reference,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Diperiksa {$checked} transaksi; {$closed} ditutup secara lokal.");

        return self::SUCCESS;
    }
}
