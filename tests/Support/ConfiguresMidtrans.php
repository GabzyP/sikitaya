<?php

namespace Tests\Support;

use App\Contracts\MidtransGateway;
use App\Enums\MidtransMerchantStatus;
use App\Models\Store;
use Illuminate\Support\Str;

trait ConfiguresMidtrans
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function configureMidtrans(array $overrides = []): FakeMidtransGateway
    {
        $config = array_replace_recursive([
            'enabled' => true,
            'checkout_enabled' => true,
            'environment' => 'sandbox',
            'production_enabled' => false,
            'server_key' => 'SB-Mid-server-test',
            'client_key' => 'SB-Mid-client-test',
            'platform_merchant_id' => 'PLATFORM-MERCHANT-TEST',
            'notification_url' => null,
            'expiry_minutes' => 60,
            'commission_percent' => 0,
            'split' => [
                'enabled' => true,
            ],
            'disbursement' => [
                'enabled' => false,
                'base_url' => 'https://app.sandbox.midtrans.com/iris/api/v1',
                'creator_key' => null,
                'approver_key' => null,
                'auto_approve' => false,
                'default_email' => 'finance@sikitaya.test',
                'timeout' => 30,
            ],
        ], $overrides);

        config(['midtrans' => $config]);

        $fake = new FakeMidtransGateway;
        $this->app->instance(MidtransGateway::class, $fake);

        return $fake;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createSplitReadyStore(array $overrides = []): Store
    {
        return Store::factory()->create(array_merge([
            'midtrans_merchant_id' => 'MERCHANT-'.Str::upper(Str::random(8)),
            'midtrans_merchant_status' => MidtransMerchantStatus::Registered,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createDisbursementReadyStore(array $overrides = []): Store
    {
        $store = Store::factory()->create($overrides);

        $store->update([
            'payout_bank_code' => 'bca',
            'payout_bank_name' => 'Bank Central Asia',
            'payout_account_number' => '1234567890',
            'payout_account_name' => 'Pemilik Toko',
            'payout_account_verified_at' => now(),
        ]);

        return $store->refresh();
    }
}
