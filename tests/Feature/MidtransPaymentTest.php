<?php

namespace Tests\Feature;

use App\Enums\MidtransMerchantStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAllocationMethod;
use App\Enums\PaymentAllocationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentAllocation;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Support\ConfiguresMidtrans;
use Tests\TestCase;

class MidtransPaymentTest extends TestCase
{
    use ConfiguresMidtrans;
    use RefreshDatabase;

    public function test_multi_store_checkout_creates_one_combined_transaction_and_split_allocations(): void
    {
        $fake = $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $storeA = $this->createSplitReadyStore(['name' => 'Toko Kopi Medan']);
        $storeB = $this->createSplitReadyStore(['name' => 'Toko Tenun Deli']);

        $productA = Product::factory()->for($storeA)->create([
            'name' => 'Kopi Arabika',
            'price' => 100_000,
            'stock' => 10,
            'weight' => 1_000,
        ]);
        $productB = Product::factory()->for($storeB)->create([
            'name' => 'Ulos Sadum',
            'price' => 150_000,
            'stock' => 5,
            'weight' => 1_500,
        ]);

        CartItem::factory()->for($buyer)->for($productA)->create(['quantity' => 2]);
        CartItem::factory()->for($buyer)->for($productB)->create(['quantity' => 1]);

        $response = $this->postCheckout($buyer, [$storeA, $storeB]);

        $transaction = PaymentTransaction::query()->with('allocations', 'orders')->firstOrFail();
        $response->assertRedirect(route('payments.show', $transaction))->assertSessionHas('success');

        $this->assertSame(PaymentTransactionStatus::Pending, $transaction->status);
        $this->assertSame(PaymentAllocationMethod::Split, $transaction->allocation_method);
        $this->assertSame((int) $transaction->orders->sum('total'), $transaction->gross_amount);
        $this->assertSame(0, $transaction->platform_commission);
        $this->assertSame(0, $transaction->midtrans_fee);
        $this->assertSame('platform', $transaction->fee_bearer);
        $this->assertCount(2, $transaction->orders);
        $this->assertCount(2, $transaction->allocations);

        foreach ($transaction->orders as $order) {
            $this->assertSame($transaction->id, $order->payment_transaction_id);
            $this->assertSame('midtrans', $order->payment_method->value);
            $this->assertSame(OrderStatus::Menunggu, $order->status);
        }

        foreach ($transaction->allocations as $allocation) {
            $order = $transaction->orders->firstWhere('id', $allocation->order_id);

            $this->assertSame(PaymentAllocationMethod::Split, $allocation->method);
            $this->assertSame(PaymentAllocationStatus::Pending, $allocation->status);
            $this->assertSame($order->total, $allocation->gross_amount);
            $this->assertSame($order->total, $allocation->net_amount);
            $this->assertSame(0, $allocation->platform_commission);
            $this->assertSame($order->store->midtrans_merchant_id, $allocation->merchant_id);
            $this->assertNull($allocation->payout_account_number);
        }

        $this->assertSame(8, $productA->refresh()->stock);
        $this->assertSame(4, $productB->refresh()->stock);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertCount(1, $fake->snapPayloads);

        $payload = $fake->snapPayloads[0];
        $this->assertSame($transaction->midtrans_order_id, $payload['transaction_details']['order_id']);
        $this->assertSame($transaction->gross_amount, $payload['transaction_details']['gross_amount']);
        $this->assertSame(
            $transaction->gross_amount,
            (int) collect($payload['item_details'])->sum(fn (array $item) => $item['price'] * $item['quantity'])
        );

        $split = $payload['transaction_split_settings'];
        $this->assertSame('fixed', $split['gross_amount_split']['type']);
        $this->assertSame(config('midtrans.platform_merchant_id'), $split['gross_amount_split']['remaining_split_receiver']);
        $this->assertSame($transaction->gross_amount, (int) collect($split['gross_amount_split']['split_rules'])->sum(fn (array $rule) => (int) $rule['value']));
        $this->assertEqualsCanonicalizing(
            $transaction->allocations->pluck('merchant_id')->all(),
            collect($split['gross_amount_split']['split_rules'])->pluck('merchant_id')->all()
        );
        $this->assertSame(config('midtrans.platform_merchant_id'), $split['fee_split']['split_rules'][0]['merchant_id']);
        $this->assertSame('100', $split['fee_split']['split_rules'][0]['value']);
        $this->assertSame('percentage', $split['fee_split']['type']);
    }

    public function test_settlement_notification_marks_all_orders_paid_once_and_is_idempotent(): void
    {
        $fake = $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $storeA = $this->createSplitReadyStore(['name' => 'Toko Pertama']);
        $storeB = $this->createSplitReadyStore(['name' => 'Toko Kedua']);

        $this->addProductToCart($buyer, $storeA, ['price' => 75_000, 'stock' => 10]);
        $this->addProductToCart($buyer, $storeB, ['price' => 90_000, 'stock' => 8]);
        $this->postCheckout($buyer, [$storeA, $storeB])->assertSessionHas('success');

        $transaction = PaymentTransaction::query()->firstOrFail();
        $payload = $this->notificationPayload($transaction, 'settlement');

        $this->postJson(route('midtrans.notification'), $payload)
            ->assertOk()
            ->assertJsonPath('status', 'OK')
            ->assertJsonPath('outcome', 'paid');

        $transaction->refresh()->load('orders', 'allocations');
        $this->assertSame(PaymentTransactionStatus::Paid, $transaction->status);
        $this->assertNotNull($transaction->paid_at);
        $this->assertSame(2, $transaction->orders->where('status', OrderStatus::Dibayar)->count());
        $this->assertTrue($transaction->orders->every(fn (Order $order) => $order->paid_at !== null));
        $this->assertTrue($transaction->allocations->every(fn (PaymentAllocation $allocation) => $allocation->status === PaymentAllocationStatus::Completed));
        $this->assertTrue($transaction->allocations->every(fn (PaymentAllocation $allocation) => $allocation->disbursed_amount === $allocation->net_amount));
        $this->assertSame(1, $transaction->notification_count);
        $this->assertDatabaseHas('payment_webhook_logs', [
            'payment_transaction_id' => $transaction->id,
            'signature_valid' => true,
            'processed' => true,
        ]);

        $this->postJson(route('midtrans.notification'), $payload)
            ->assertOk()
            ->assertJsonPath('outcome', 'already_paid');

        $transaction->refresh()->load('orders', 'allocations');
        $this->assertSame(PaymentTransactionStatus::Paid, $transaction->status);
        $this->assertSame(2, $transaction->notification_count);
        $this->assertSame(2, $transaction->orders->where('status', OrderStatus::Dibayar)->count());
        $this->assertSame(2, $transaction->allocations->count());
        $this->assertTrue($transaction->allocations->every(fn (PaymentAllocation $allocation) => $allocation->disbursed_amount === $allocation->net_amount));
        $this->assertCount(1, $fake->snapPayloads);
    }

    public function test_notification_rejects_invalid_signature_and_does_not_mark_orders_paid(): void
    {
        $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $store = $this->createSplitReadyStore();
        $this->addProductToCart($buyer, $store);
        $this->postCheckout($buyer, [$store]);

        $transaction = PaymentTransaction::query()->firstOrFail();
        $payload = $this->notificationPayload($transaction, 'settlement');
        $payload['signature_key'] = str_repeat('0', 128);

        $this->postJson(route('midtrans.notification'), $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid signature.');

        $this->assertSame(PaymentTransactionStatus::Pending, $transaction->refresh()->status);
        $this->assertSame(0, $transaction->orders()->where('status', OrderStatus::Dibayar)->count());
        $this->assertDatabaseHas('payment_webhook_logs', [
            'payment_transaction_id' => $transaction->id,
            'signature_valid' => false,
            'processed' => false,
        ]);
    }

    public function test_notification_rejects_amount_mismatch_and_does_not_mark_orders_paid(): void
    {
        $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $store = $this->createSplitReadyStore();
        $this->addProductToCart($buyer, $store);
        $this->postCheckout($buyer, [$store]);

        $transaction = PaymentTransaction::query()->firstOrFail();
        $payload = $this->notificationPayload($transaction, 'settlement', $transaction->gross_amount + 1);

        $this->postJson(route('midtrans.notification'), $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nominal notifikasi Midtrans tidak sesuai dengan transaksi tersimpan.');

        $this->assertSame(PaymentTransactionStatus::Pending, $transaction->refresh()->status);
        $this->assertSame(0, $transaction->orders()->where('status', OrderStatus::Dibayar)->count());
    }

    public function test_expired_notification_restores_stock_exactly_once(): void
    {
        $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $store = $this->createSplitReadyStore();
        $product = Product::factory()->for($store)->create(['stock' => 5, 'weight' => 1_000]);
        CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 2]);
        $this->postCheckout($buyer, [$store]);

        $transaction = PaymentTransaction::query()->firstOrFail();
        $this->assertSame(3, $product->refresh()->stock);

        $payload = $this->notificationPayload($transaction, 'expire', $transaction->gross_amount, [
            'status_code' => '407',
            'status_message' => 'Transaction is expired.',
        ]);

        $this->postJson(route('midtrans.notification'), $payload)->assertOk();
        $transaction->refresh()->load('allocations');
        $this->assertSame(PaymentTransactionStatus::Expired, $transaction->status);
        $this->assertNotNull($transaction->stock_restored_at);
        $this->assertSame(5, $product->refresh()->stock);
        $this->assertTrue($transaction->allocations->every(fn (PaymentAllocation $allocation) => $allocation->status === PaymentAllocationStatus::Cancelled));

        $this->postJson(route('midtrans.notification'), $payload)->assertOk();
        $transaction->refresh();
        $this->assertSame(5, $product->refresh()->stock);
        $this->assertSame(2, $transaction->notification_count);
        $this->assertSame(5, $product->refresh()->stock);

        $this->postJson(route('midtrans.notification'), $this->notificationPayload($transaction, 'pending'))
            ->assertOk()
            ->assertJsonPath('outcome', 'ignored_terminal');
        $this->assertSame(PaymentTransactionStatus::Expired, $transaction->refresh()->status);

        $this->postJson(route('midtrans.notification'), $this->notificationPayload($transaction, 'cancel'))
            ->assertOk()
            ->assertJsonPath('outcome', 'ignored_terminal');
        $this->assertSame(PaymentTransactionStatus::Expired, $transaction->refresh()->status);
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_retry_after_terminal_state_re_reserves_stock_creates_new_order_id_and_rejects_stale_callback(): void
    {
        $fake = $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $store = $this->createSplitReadyStore();
        $product = Product::factory()->for($store)->create(['stock' => 5, 'weight' => 1_000]);
        CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 2]);
        $this->postCheckout($buyer, [$store]);

        $transaction = PaymentTransaction::query()->firstOrFail();
        $oldOrderId = $transaction->midtrans_order_id;

        $this->postJson(route('midtrans.notification'), $this->notificationPayload($transaction, 'expire', $transaction->gross_amount, ['status_code' => '407']))->assertOk();
        $this->assertSame(PaymentTransactionStatus::Expired, $transaction->refresh()->status);
        $this->assertSame(5, $product->refresh()->stock);

        $this->actingAs($buyer)
            ->post(route('payments.retry', $transaction))
            ->assertRedirect()
            ->assertSessionHas('success');

        $transaction->refresh()->load('attempts', 'allocations');
        $this->assertSame(PaymentTransactionStatus::Pending, $transaction->status);
        $this->assertNull($transaction->stock_restored_at);
        $this->assertNotSame($oldOrderId, $transaction->midtrans_order_id);
        $this->assertSame(3, $product->refresh()->stock);
        $this->assertCount(2, $transaction->attempts);
        $this->assertSame(2, $transaction->attempts->max('attempt_number'));
        $this->assertSame($transaction->midtrans_order_id, $transaction->attempts->sortByDesc('attempt_number')->first()->midtrans_order_id);
        $this->assertCount(2, $fake->snapPayloads);
        $this->assertTrue($transaction->allocations->every(fn (PaymentAllocation $allocation) => $allocation->status === PaymentAllocationStatus::Pending));
        $this->assertDatabaseHas('payment_allocation_events', [
            'payment_allocation_id' => $transaction->allocations->first()->id,
            'action' => 'payment_reopened',
        ]);

        $stale = $this->notificationPayload($transaction, 'settlement', $transaction->gross_amount, [
            'order_id' => $oldOrderId,
            'transaction_id' => 'STALE-TRX-1',
        ]);

        $this->postJson(route('midtrans.notification'), $stale)
            ->assertStatus(422)
            ->assertJsonPath('message', 'ID pesanan Midtrans bukan percobaan pembayaran terbaru untuk transaksi ini.');

        $this->assertSame(PaymentTransactionStatus::Pending, $transaction->refresh()->status);
        $this->assertSame(3, $product->refresh()->stock);
    }

    public function test_disbursement_flow_requires_admin_approval_and_uses_gateway_status(): void
    {
        $fake = $this->configureMidtrans([
            'split' => ['enabled' => false],
            'disbursement' => [
                'enabled' => true,
                'creator_key' => 'IRIS-CREATOR-TEST',
                'approver_key' => null,
                'auto_approve' => false,
            ],
        ]);
        $fake->payoutResponse = [
            'payouts' => [[
                'reference_no' => 'IRIS-PAYOUT-TEST-1',
                'status' => 'queued',
            ]],
        ];

        $buyer = User::factory()->buyer()->create();
        $admin = User::factory()->admin()->create();
        $store = $this->createDisbursementReadyStore(['name' => 'Toko Disbursement']);
        $product = Product::factory()->for($store)->create(['stock' => 5, 'weight' => 1_000]);
        CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 1]);

        $this->postCheckout($buyer, [$store])->assertSessionHas('success');
        $transaction = PaymentTransaction::query()->with('allocations')->firstOrFail();
        $allocation = $transaction->allocations->firstOrFail();

        $this->assertSame(PaymentAllocationMethod::Disbursement, $transaction->allocation_method);
        $this->assertSame(PaymentAllocationMethod::Disbursement, $allocation->method);
        $this->assertSame(0, $allocation->disbursed_amount);
        $this->assertSame('1234567890', $allocation->payout_account_number);

        $this->postJson(route('midtrans.notification'), $this->notificationPayload($transaction, 'settlement'))->assertOk();
        $allocation->refresh();
        $this->assertSame(PaymentAllocationStatus::Ready, $allocation->status);
        $this->assertSame(0, $allocation->disbursed_amount);

        $this->actingAs($store->owner)
            ->post(route('admin.allocations.approve', $allocation))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.allocations.approve', $allocation))
            ->assertRedirect()
            ->assertSessionHas('success');

        $allocation->refresh();
        $this->assertSame(PaymentAllocationStatus::Approved, $allocation->status);
        $this->assertSame($admin->id, $allocation->approved_by);

        $this->actingAs($admin)
            ->post(route('admin.allocations.send', $allocation))
            ->assertRedirect()
            ->assertSessionHas('success');

        $allocation->refresh();
        $this->assertSame(PaymentAllocationStatus::Processing, $allocation->status);
        $this->assertSame('IRIS-PAYOUT-TEST-1', $allocation->midtrans_reference);
        $this->assertSame(1, $allocation->attempt_count);
        $this->assertSame(0, $allocation->disbursed_amount);
        $this->assertCount(1, $fake->payoutRequests);
        $this->assertSame((string) $allocation->net_amount, $fake->payoutRequests[0]['amount']);
        $this->assertSame($store->payout_bank_code, $fake->payoutRequests[0]['beneficiary_bank']);
        $this->assertSame('1234567890', $fake->payoutRequests[0]['beneficiary_account']);
        $this->assertStringStartsWith('DISB-', $fake->payoutIdempotencyKeys[0]);
        $this->assertLessThanOrEqual(46, strlen($fake->payoutIdempotencyKeys[0]));
        $this->assertSame($fake->payoutIdempotencyKeys[0], $allocation->metadata['disbursement_idempotency_key']);

        $fake->payoutStatusResponse = [
            'payouts' => [[
                'reference_no' => 'IRIS-PAYOUT-TEST-1',
                'status' => 'completed',
            ]],
        ];

        $this->actingAs($admin)
            ->post(route('admin.allocations.sync', $allocation))
            ->assertRedirect()
            ->assertSessionHas('success');

        $allocation->refresh();
        $this->assertSame(PaymentAllocationStatus::Completed, $allocation->status);
        $this->assertSame($allocation->net_amount, $allocation->disbursed_amount);
        $this->assertNotNull($allocation->completed_at);

        $this->actingAs($admin)
            ->post(route('admin.allocations.sync', $allocation))
            ->assertRedirect();

        $allocation->refresh();
        $this->assertSame($allocation->net_amount, $allocation->disbursed_amount);
        $this->assertSame(PaymentAllocationStatus::Completed, $allocation->status);
    }

    public function test_retry_after_failed_payout_reuses_the_same_idempotency_key(): void
    {
        $fake = $this->configureMidtrans([
            'split' => ['enabled' => false],
            'disbursement' => [
                'enabled' => true,
                'creator_key' => 'IRIS-CREATOR-TEST',
                'approver_key' => null,
                'auto_approve' => false,
            ],
        ]);

        $buyer = User::factory()->buyer()->create();
        $admin = User::factory()->admin()->create();
        $store = $this->createDisbursementReadyStore();
        $this->addProductToCart($buyer, $store);
        $this->postCheckout($buyer, [$store]);
        $transaction = PaymentTransaction::query()->firstOrFail();
        $allocation = $transaction->allocations()->firstOrFail();

        $this->postJson(route('midtrans.notification'), $this->notificationPayload($transaction, 'settlement'))->assertOk();
        $this->actingAs($admin)->post(route('admin.allocations.approve', $allocation))->assertSessionHas('success');

        $fake->payoutException = new RuntimeException('Koneksi ke Iris terputus.');

        $this->actingAs($admin)
            ->post(route('admin.allocations.send', $allocation))
            ->assertRedirect()
            ->assertSessionHas('error');

        $allocation->refresh();
        $this->assertSame(PaymentAllocationStatus::Failed, $allocation->status);
        $this->assertSame(0, $allocation->disbursed_amount);
        $firstKey = (string) data_get($allocation->metadata, 'disbursement_idempotency_key');
        $this->assertStringStartsWith('DISB-', $firstKey);

        $fake->payoutException = null;

        $this->actingAs($admin)
            ->post(route('admin.allocations.send', $allocation))
            ->assertRedirect()
            ->assertSessionHas('success');

        $allocation->refresh();
        $this->assertSame(PaymentAllocationStatus::Processing, $allocation->status);
        $this->assertCount(2, $fake->payoutIdempotencyKeys);
        $this->assertSame($firstKey, $fake->payoutIdempotencyKeys[1]);
        $this->assertSame($firstKey, $allocation->metadata['disbursement_idempotency_key']);
    }

    public function test_repeated_checkout_submission_with_same_token_does_not_duplicate_orders_or_payment(): void
    {
        $fake = $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $store = $this->createSplitReadyStore();
        $product = Product::factory()->for($store)->create(['stock' => 5, 'weight' => 1_000]);
        CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 1]);

        $token = (string) Str::uuid();
        $payload = [
            'checkout_token' => $token,
            'recipient_name' => 'Pembeli Medan',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1, Medan',
            'city_id' => 278,
            'postal_code' => '20211',
            'payment_method' => 'midtrans',
            'shipping' => [$store->id => 'jne|REG'],
        ];

        $this->actingAs($buyer)->withSession(['checkout_token' => $token])->post(route('checkout.store'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $transaction = PaymentTransaction::query()->firstOrFail();

        $this->actingAs($buyer)->withSession(['checkout_token' => $token])->post(route('checkout.store'), $payload)
            ->assertRedirect(route('payments.show', $transaction))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertCount(1, $fake->snapPayloads);
        $this->assertSame(4, $product->refresh()->stock);
    }

    public function test_capture_with_fraud_deny_is_terminal_and_restores_stock(): void
    {
        $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $store = $this->createSplitReadyStore();
        $product = Product::factory()->for($store)->create(['stock' => 5, 'weight' => 1_000]);
        CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 1]);
        $this->postCheckout($buyer, [$store]);

        $transaction = PaymentTransaction::query()->firstOrFail();
        $payload = $this->notificationPayload($transaction, 'capture', $transaction->gross_amount, [
            'fraud_status' => 'deny',
        ]);

        $this->postJson(route('midtrans.notification'), $payload)
            ->assertOk()
            ->assertJsonPath('outcome', 'failed');

        $transaction->refresh();
        $this->assertSame(PaymentTransactionStatus::Failed, $transaction->status);
        $this->assertNotNull($transaction->stock_restored_at);
        $this->assertSame(5, $product->refresh()->stock);
        $this->assertSame(0, $transaction->orders()->where('status', OrderStatus::Dibayar)->count());
    }

    public function test_admin_can_update_store_payment_settings_verify_account_and_reset_verification_on_change(): void
    {
        $fake = $this->configureMidtrans([
            'split' => ['enabled' => false],
            'disbursement' => [
                'enabled' => true,
                'creator_key' => 'IRIS-CREATOR-TEST',
            ],
        ]);
        $fake->bankValidationResponse = [
            'status' => 'valid',
            'account_name' => 'Pemilik Toko',
        ];

        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create(['name' => 'Toko Rekening']);

        $this->actingAs($admin)
            ->put(route('admin.store-payments.update', $store), [
                'midtrans_merchant_id' => 'MERCHANT-SETTINGS-1',
                'midtrans_merchant_status' => 'registered',
                'payout_bank_code' => 'bca',
                'payout_bank_name' => 'Bank Central Asia',
                'payout_account_number' => '1234567890',
                'payout_account_name' => 'Pemilik Toko',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $store->refresh();
        $this->assertSame('MERCHANT-SETTINGS-1', $store->midtrans_merchant_id);
        $this->assertSame(MidtransMerchantStatus::Registered, $store->midtrans_merchant_status);
        $this->assertSame('1234567890', $store->payout_account_number);
        $this->assertNotSame('1234567890', DB::table('stores')->where('id', $store->id)->value('payout_account_number'));

        $this->actingAs($admin)
            ->post(route('admin.store-payments.verify', $store))
            ->assertRedirect()
            ->assertSessionHas('success');

        $store->refresh();
        $this->assertNotNull($store->payout_account_verified_at);
        $this->assertSame($admin->id, $store->payout_account_verified_by);
        $this->assertCount(1, $fake->bankValidationRequests);
        $this->assertSame('bca', $fake->bankValidationRequests[0]['bank']);
        $this->assertSame('1234567890', $fake->bankValidationRequests[0]['account']);

        $this->actingAs($admin)
            ->put(route('admin.store-payments.update', $store), [
                'midtrans_merchant_id' => 'MERCHANT-SETTINGS-1',
                'midtrans_merchant_status' => 'registered',
                'payout_bank_code' => 'bca',
                'payout_bank_name' => 'Bank Central Asia',
                'payout_account_number' => '9999999999',
                'payout_account_name' => 'Pemilik Toko',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $store->refresh();
        $this->assertNull($store->payout_account_verified_at);
        $this->assertNull($store->payout_account_verified_by);
        $this->assertSame('9999999999', $store->payout_account_number);
    }

    public function test_midtrans_order_is_protected_from_manual_confirmation_and_proof_upload(): void
    {
        Storage::fake('public');

        $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $otherBuyer = User::factory()->buyer()->create();
        $store = $this->createSplitReadyStore();
        $this->addProductToCart($buyer, $store);
        $this->postCheckout($buyer, [$store]);

        $transaction = PaymentTransaction::query()->firstOrFail();
        $order = $transaction->orders()->firstOrFail();

        $this->actingAs($otherBuyer)->get(route('payments.show', $transaction))->assertForbidden();

        $this->actingAs($buyer)
            ->post(route('orders.proof', $order), [
                'payment_proof' => UploadedFile::fake()->image('bukti.png'),
            ])
            ->assertForbidden();

        $this->actingAs($store->owner)
            ->post(route('seller.orders.confirm', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Menunggu, $order->refresh()->status);
        $this->assertNull($order->payment_proof);
    }

    public function test_cod_checkout_does_not_create_midtrans_transaction_or_call_gateway(): void
    {
        $fake = $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $store = $this->createSplitReadyStore();
        $this->addProductToCart($buyer, $store);

        $this->postCheckout($buyer, [$store], 'cod')->assertRedirect(route('orders.index'));

        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertCount(0, $fake->snapPayloads);
        $this->assertDatabaseHas('orders', [
            'user_id' => $buyer->id,
            'payment_method' => 'cod',
            'status' => 'menunggu',
        ]);
    }

    public function test_online_checkout_is_blocked_when_split_and_disbursement_are_not_enabled(): void
    {
        $this->configureMidtrans([
            'split' => ['enabled' => false],
            'disbursement' => ['enabled' => false],
        ]);

        $buyer = User::factory()->buyer()->create();
        $store = Store::factory()->create(['name' => 'Toko Tanpa Kanal']);
        $product = Product::factory()->for($store)->create(['stock' => 5, 'weight' => 1_000]);
        CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 2]);

        $this->actingAs($buyer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Midtrans belum siap digunakan')
            ->assertSee('Transfer Bank')
            ->assertSee('QRIS')
            ->assertSee('COD')
            ->assertDontSee('id="payment-midtrans"', false);

        $token = (string) Str::uuid();

        $this->actingAs($buyer)
            ->withSession(['checkout_token' => $token])
            ->post(route('checkout.store'), [
                'checkout_token' => $token,
                'recipient_name' => 'Pembeli Medan',
                'phone' => '081234567890',
                'address' => 'Jl. Merdeka No. 1, Medan',
                'city_id' => 278,
                'postal_code' => '20211',
                'payment_method' => 'midtrans',
                'shipping' => [$store->id => 'jne|REG'],
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertSame(5, $product->refresh()->stock);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_midtrans_checkout_is_hidden_and_blocked_when_checkout_flag_is_disabled(): void
    {
        $fake = $this->configureMidtrans(['checkout_enabled' => false]);
        $buyer = User::factory()->buyer()->create();
        $store = $this->createSplitReadyStore(['name' => 'Toko Midtrans Tertunda']);
        $product = $this->addProductToCart($buyer, $store);

        $this->actingAs($buyer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Transfer Bank')
            ->assertSee('QRIS')
            ->assertSee('Pembayaran manual (simulasi) aktif')
            ->assertDontSee('id="payment-midtrans"', false);

        $this->postCheckout($buyer, [$store])
            ->assertSessionHasErrors('payment_method');

        $this->assertCount(0, $fake->snapPayloads);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(10, $product->refresh()->stock);
    }

    public function test_checkout_page_offers_midtrans_when_flag_and_prerequisites_are_ready(): void
    {
        $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $store = $this->createSplitReadyStore(['name' => 'Toko Midtrans Siap']);
        $this->addProductToCart($buyer, $store);

        $this->actingAs($buyer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Midtrans Online')
            ->assertSee('id="payment-midtrans"', false)
            ->assertSee('Pembayaran online Midtrans aktif');
    }

    public function test_admin_payment_pages_and_seller_visibility_are_rendered_correctly(): void
    {
        $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $admin = User::factory()->admin()->create();
        $storeA = $this->createSplitReadyStore(['name' => 'Toko Terlihat']);
        $storeB = $this->createSplitReadyStore(['name' => 'Toko Rahasia']);
        $productA = Product::factory()->for($storeA)->create(['price' => 40_000, 'stock' => 5, 'weight' => 1_000]);
        $productB = Product::factory()->for($storeB)->create(['price' => 60_000, 'stock' => 5, 'weight' => 1_000]);
        CartItem::factory()->for($buyer)->for($productA)->create(['quantity' => 1]);
        CartItem::factory()->for($buyer)->for($productB)->create(['quantity' => 1]);

        $this->postCheckout($buyer, [$storeA, $storeB]);
        $transaction = PaymentTransaction::query()->firstOrFail();
        $this->postJson(route('midtrans.notification'), $this->notificationPayload($transaction, 'settlement'))->assertOk();

        $orderA = $transaction->orders()->where('store_id', $storeA->id)->firstOrFail();
        $orderB = $transaction->orders()->where('store_id', $storeB->id)->firstOrFail();

        $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk()->assertSee($transaction->reference);
        $this->actingAs($admin)->get(route('admin.payments.show', $transaction))->assertOk()
            ->assertSee($transaction->reference)
            ->assertSee('Toko Terlihat')
            ->assertSee('Toko Rahasia');
        $this->actingAs($admin)->get(route('admin.store-payments.edit', $storeA))->assertOk()->assertSee('Merchant ID Midtrans');

        $this->actingAs($buyer)->get(route('payments.show', $transaction))->assertOk()
            ->assertSee($transaction->reference)
            ->assertSee('Alokasi dana per penjual');

        $this->actingAs($storeA->owner)->get(route('seller.orders.show', $orderA))->assertOk()
            ->assertSee('Alokasi dana')
            ->assertSee('Toko Terlihat')
            ->assertDontSee('Toko Rahasia');

        $this->actingAs($storeA->owner)->get(route('seller.orders.show', $orderB))->assertForbidden();
    }

    public function test_scheduler_closes_pending_transaction_whose_snap_creation_failed_and_restores_stock(): void
    {
        $fake = $this->configureMidtrans();
        $fake->snapException = new RuntimeException('Snap sedang tidak tersedia.');

        $buyer = User::factory()->buyer()->create();
        $store = $this->createSplitReadyStore();
        $product = Product::factory()->for($store)->create(['stock' => 5, 'weight' => 1_000]);
        CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 2]);

        $this->postCheckout($buyer, [$store])->assertSessionHas('error');
        $transaction = PaymentTransaction::query()->firstOrFail();
        $this->assertSame(PaymentTransactionStatus::Pending, $transaction->status);
        $this->assertSame(3, $product->refresh()->stock);
        $this->assertDatabaseHas('payment_attempts', [
            'payment_transaction_id' => $transaction->id,
            'status' => 'failed',
        ]);

        $transaction->update(['expires_at' => now()->subMinute()]);

        $this->artisan('payments:expire')->assertSuccessful();

        $transaction->refresh();
        $this->assertSame(PaymentTransactionStatus::Expired, $transaction->status);
        $this->assertNotNull($transaction->stock_restored_at);
        $this->assertSame(5, $product->refresh()->stock);

        $this->artisan('payments:expire')->assertSuccessful();
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_buyer_sync_uses_get_status_api_and_marks_payment_paid(): void
    {
        $fake = $this->configureMidtrans();
        $buyer = User::factory()->buyer()->create();
        $store = $this->createSplitReadyStore();
        $this->addProductToCart($buyer, $store);
        $this->postCheckout($buyer, [$store]);

        $transaction = PaymentTransaction::query()->firstOrFail();
        $fake->statusResponse = $this->notificationPayload($transaction, 'settlement');
        unset($fake->statusResponse['signature_key']);

        $this->actingAs($buyer)
            ->post(route('payments.sync', $transaction))
            ->assertRedirect()
            ->assertSessionHas('success');

        $transaction->refresh();
        $this->assertSame(PaymentTransactionStatus::Paid, $transaction->status);
        $this->assertSame([$transaction->midtrans_order_id], $fake->statusRequests);
        $this->assertSame(1, $transaction->orders()->where('status', OrderStatus::Dibayar)->count());
    }

    /**
     * @param  list<Store>  $stores
     */
    private function postCheckout(User $buyer, array $stores, string $paymentMethod = 'midtrans'): TestResponse
    {
        $token = (string) Str::uuid();
        $shipping = [];

        foreach ($stores as $store) {
            $shipping[$store->id] = 'jne|REG';
        }

        return $this->actingAs($buyer)
            ->withSession(['checkout_token' => $token])
            ->post(route('checkout.store'), [
                'checkout_token' => $token,
                'recipient_name' => 'Pembeli Medan',
                'phone' => '081234567890',
                'address' => 'Jl. Merdeka No. 1, Medan',
                'city_id' => 278,
                'postal_code' => '20211',
                'payment_method' => $paymentMethod,
                'shipping' => $shipping,
            ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function addProductToCart(User $buyer, Store $store, array $attributes = []): Product
    {
        $product = Product::factory()->for($store)->create(array_merge([
            'price' => 50_000,
            'stock' => 10,
            'weight' => 1_000,
        ], $attributes));

        CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 1]);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function notificationPayload(
        PaymentTransaction $transaction,
        string $status,
        ?int $grossAmount = null,
        array $overrides = [],
    ): array {
        $payload = array_merge([
            'order_id' => $transaction->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => (string) ($grossAmount ?? $transaction->gross_amount),
            'currency' => 'IDR',
            'transaction_status' => $status,
            'transaction_id' => 'TRX-'.$transaction->id.'-'.Str::upper(Str::random(6)),
            'payment_type' => 'credit_card',
            'fraud_status' => 'accept',
            'settlement_time' => now()->toIso8601String(),
            'status_message' => 'Midtrans notification test.',
        ], $overrides);

        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('midtrans.server_key')
        );

        return $payload;
    }
}
