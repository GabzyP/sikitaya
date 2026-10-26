<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookLog;
use App\Services\MidtransPaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransPaymentService $payments): JsonResponse
    {
        $payload = $request->all();
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signature = strtolower((string) ($payload['signature_key'] ?? ''));
        $serverKey = (string) config('midtrans.server_key');

        $attempt = $orderId !== ''
            ? PaymentAttempt::with('transaction')->where('midtrans_order_id', $orderId)->first()
            : null;

        $log = PaymentWebhookLog::create([
            'payment_transaction_id' => $attempt?->payment_transaction_id,
            'midtrans_order_id' => $orderId ?: null,
            'midtrans_transaction_id' => $payload['transaction_id'] ?? null,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'gross_amount' => $grossAmount ?: null,
            'currency' => $payload['currency'] ?? null,
            'signature_valid' => false,
            'processed' => false,
            'payload' => $payload,
        ]);

        if ($serverKey === '') {
            $log->update(['message' => 'Server key Midtrans belum dikonfigurasi.']);

            return response()->json(['message' => 'Midtrans belum dikonfigurasi.'], 503);
        }

        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
        $signatureValid = $signature !== '' && hash_equals($expectedSignature, $signature);

        if (! $signatureValid) {
            $log->update(['message' => 'Signature notifikasi tidak valid.', 'signature_valid' => false]);

            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $log->update(['signature_valid' => true]);

        if (! $attempt) {
            $log->update(['message' => 'order_id Midtrans tidak ditemukan.']);

            return response()->json(['message' => 'Unknown order_id.'], 404);
        }

        try {
            $payments->assertPayloadMatchesTransaction($attempt->transaction, $payload);
            $result = $payments->applyGatewayStatus($attempt->transaction, $payload, 'notification');

            $log->update([
                'processed' => true,
                'message' => $result['message'] ?? null,
            ]);

            return response()->json([
                'status' => 'OK',
                'outcome' => $result['outcome'] ?? null,
            ]);
        } catch (DomainException $exception) {
            $log->update(['message' => $exception->getMessage()]);

            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);
            $log->update(['message' => 'Gagal memproses notifikasi: '.$exception->getMessage()]);

            return response()->json(['message' => 'Notification processing failed.'], 500);
        }
    }
}
