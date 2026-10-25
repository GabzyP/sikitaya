<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_upload_payment_proof_for_a_transfer_order(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->for($buyer)->create([
            'payment_method' => PaymentMethod::Transfer,
            'status' => OrderStatus::Menunggu,
        ]);

        $this->actingAs($buyer)
            ->post(route('orders.proof', $order), [
                'payment_proof' => UploadedFile::fake()->image('bukti-transfer.png'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertNotNull($order->payment_proof);
        $this->assertNotNull($order->payment_proof_uploaded_at);
        Storage::disk('public')->assertExists($order->payment_proof);
    }

    public function test_payment_proof_must_be_a_supported_file_type(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->for($buyer)->create(['payment_method' => PaymentMethod::Transfer]);

        $this->actingAs($buyer)
            ->post(route('orders.proof', $order), [
                'payment_proof' => UploadedFile::fake()->create('bukti.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('payment_proof');

        $this->assertNull($order->fresh()->payment_proof);
    }

    public function test_another_buyer_cannot_upload_proof_for_someone_elses_order(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->buyer()->create();
        $otherBuyer = User::factory()->buyer()->create();
        $order = Order::factory()->for($buyer)->create(['payment_method' => PaymentMethod::Transfer]);

        $this->actingAs($otherBuyer)
            ->post(route('orders.proof', $order), [
                'payment_proof' => UploadedFile::fake()->image('bukti.png'),
            ])
            ->assertForbidden();
    }

    public function test_proof_cannot_be_uploaded_for_cod_orders(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->for($buyer)->cod()->create();

        $this->actingAs($buyer)
            ->post(route('orders.proof', $order), [
                'payment_proof' => UploadedFile::fake()->image('bukti.png'),
            ])
            ->assertForbidden();
    }

    public function test_seller_can_confirm_transfer_payment_after_proof_is_uploaded(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $buyer = User::factory()->buyer()->create();

        $order = Order::factory()->for($buyer)->for($store)->withProof()->create([
            'payment_method' => PaymentMethod::Transfer,
            'status' => OrderStatus::Menunggu,
        ]);

        $this->actingAs($seller)
            ->post(route('seller.orders.confirm', $order))
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(OrderStatus::Dibayar, $order->status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_seller_cannot_confirm_transfer_payment_without_proof(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $order = Order::factory()->for($store)->create([
            'payment_method' => PaymentMethod::Transfer,
            'status' => OrderStatus::Menunggu,
        ]);

        $this->actingAs($seller)
            ->post(route('seller.orders.confirm', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Menunggu, $order->fresh()->status);
    }

    public function test_seller_cannot_confirm_an_order_from_another_store(): void
    {
        $seller = User::factory()->seller()->create();
        Store::factory()->for($seller, 'owner')->create();

        $otherStore = Store::factory()->create(['is_active' => true]);
        $order = Order::factory()->for($otherStore)->withProof()->create([
            'payment_method' => PaymentMethod::Transfer,
        ]);

        $this->actingAs($seller)
            ->post(route('seller.orders.confirm', $order))
            ->assertForbidden();
    }

    public function test_seller_can_ship_a_paid_order_and_buyer_can_complete_it(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $buyer = User::factory()->buyer()->create();

        $order = Order::factory()->for($buyer)->for($store)->paid()->create();

        $this->actingAs($seller)
            ->post(route('seller.orders.ship', $order), [
                'tracking_number' => 'JNE1234567890',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Dikirim, $order->status);
        $this->assertSame('JNE1234567890', $order->tracking_number);
        $this->assertNotNull($order->shipped_at);

        $this->actingAs($buyer)
            ->post(route('orders.complete', $order))
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Selesai, $order->status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_seller_cannot_ship_an_order_that_has_not_been_paid(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $order = Order::factory()->for($store)->create(['status' => OrderStatus::Menunggu]);

        $this->actingAs($seller)
            ->post(route('seller.orders.ship', $order), [
                'tracking_number' => 'JNE1234567890',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Menunggu, $order->fresh()->status);
    }

    public function test_shipping_requires_a_tracking_number(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $order = Order::factory()->for($store)->paid()->create();

        $this->actingAs($seller)
            ->post(route('seller.orders.ship', $order), ['tracking_number' => ''])
            ->assertSessionHasErrors('tracking_number');

        $this->assertSame(OrderStatus::Dibayar, $order->fresh()->status);
    }

    public function test_buyer_cannot_complete_an_order_before_it_is_shipped(): void
    {
        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->for($buyer)->paid()->create();

        $this->actingAs($buyer)
            ->post(route('orders.complete', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Dibayar, $order->fresh()->status);
    }

    public function test_another_buyer_cannot_complete_someone_elses_order(): void
    {
        $buyer = User::factory()->buyer()->create();
        $otherBuyer = User::factory()->buyer()->create();
        $order = Order::factory()->for($buyer)->shipped()->create();

        $this->actingAs($otherBuyer)
            ->post(route('orders.complete', $order))
            ->assertForbidden();
    }

    public function test_buyer_order_detail_page_renders_with_items(): void
    {
        $buyer = User::factory()->buyer()->create();
        $store = Store::factory()->create(['name' => 'Toko Detail']);
        $order = Order::factory()->for($buyer)->for($store)->withProof()->create([
            'status' => OrderStatus::Menunggu,
        ]);
        OrderItem::factory()->for($order)->create(['product_name' => 'Produk Detail']);

        $this->actingAs($buyer)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Produk Detail')
            ->assertSee('Toko Detail');
    }

    public function test_seller_order_detail_page_renders_with_items(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $order = Order::factory()->for($store)->withProof()->create();
        OrderItem::factory()->for($order)->create(['product_name' => 'Produk Pesanan']);

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Produk Pesanan');
    }

    public function test_cod_order_is_paid_when_the_buyer_completes_it(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $buyer = User::factory()->buyer()->create();

        $order = Order::factory()->for($buyer)->for($store)->cod()->create([
            'status' => OrderStatus::Menunggu,
        ]);

        $this->actingAs($seller)
            ->post(route('seller.orders.confirm', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(OrderStatus::Dibayar, $order->status);
        $this->assertNull($order->paid_at);

        $this->actingAs($seller)
            ->post(route('seller.orders.ship', $order), [
                'tracking_number' => 'JNE-CO-0001',
            ])
            ->assertRedirect();

        $this->actingAs($buyer)
            ->post(route('orders.complete', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(OrderStatus::Selesai, $order->status);
        $this->assertNotNull($order->paid_at);
    }
}
