<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MidtransMerchantStatus;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\PaymentAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class StorePaymentController extends Controller
{
    public function edit(Store $store): View
    {
        $store->load('owner');

        return view('admin.stores.payment', compact('store'));
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate([
            'midtrans_merchant_id' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('stores', 'midtrans_merchant_id')->ignore($store->id),
            ],
            'midtrans_merchant_status' => ['required', Rule::enum(MidtransMerchantStatus::class)],
            'payout_bank_code' => ['nullable', 'required_with:payout_account_number,payout_account_name', 'string', 'max:20'],
            'payout_bank_name' => ['nullable', 'required_with:payout_account_number,payout_account_name', 'string', 'max:100'],
            'payout_account_number' => ['nullable', 'required_with:payout_bank_code,payout_bank_name,payout_account_name', 'string', 'max:50', 'regex:/^[0-9]+$/'],
            'payout_account_name' => ['nullable', 'required_with:payout_bank_code,payout_bank_name,payout_account_number', 'string', 'max:100'],
        ], [
            'payout_account_number.regex' => 'Nomor rekening hanya boleh berisi angka.',
        ]);

        foreach (['midtrans_merchant_id', 'payout_bank_code', 'payout_bank_name', 'payout_account_number', 'payout_account_name'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
        }

        $oldBank = [
            $store->payout_bank_code,
            $store->payout_bank_name,
            $store->payout_account_number,
            $store->payout_account_name,
        ];

        $newBank = [
            $data['payout_bank_code'],
            $data['payout_bank_name'],
            $data['payout_account_number'],
            $data['payout_account_name'],
        ];

        if ($oldBank !== $newBank) {
            $data['payout_account_verified_at'] = null;
            $data['payout_account_verified_by'] = null;
        }

        $store->update($data);

        return back()->with('success', 'Pengaturan pembayaran toko '.$store->name.' berhasil disimpan.');
    }

    public function verify(Store $store, PaymentAllocationService $allocations): RedirectResponse
    {
        try {
            $allocations->verifyStoreAccount($store, auth()->user());
        } catch (Throwable $exception) {
            return back()->with('error', $this->message($exception));
        }

        return back()->with('success', 'Rekening penerima toko '.$store->name.' berhasil diverifikasi oleh Midtrans.');
    }

    private function message(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return $exception->validator->errors()->first();
        }

        return $exception->getMessage();
    }
}
