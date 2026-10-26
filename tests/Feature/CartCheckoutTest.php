<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_add_a_product_to_the_cart(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = Product::factory()->create(['stock' => 10]);

        $this->actingAs($buyer)
            ->post(route('cart.store'), [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_seller_cannot_buy_a_product_from_their_own_store(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $product = Product::factory()->for($store)->create();

        $this->actingAs($seller)
            ->post(route('cart.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_buyer_cannot_add_more_than_available_stock(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = Product::factory()->create(['stock' => 3]);

        $this->actingAs($buyer)
            ->post(route('cart.store'), [
                'product_id' => $product->id,
                'quantity' => 4,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_creates_one_order_per_store_and_clears_the_cart(): void
    {
        $buyer = User::factory()->buyer()->create();

        $storeA = Store::factory()->create(['city_id' => 278, 'city_name' => 'Kota Medan']);
        $productA = Product::factory()->for($storeA)->create([
            'price' => 50_000,
            'stock' => 10,
            'weight' => 1_000,
        ]);

        $storeB = Store::factory()->create(['city_id' => 278, 'city_name' => 'Kota Medan']);
        $productB = Product::factory()->for($storeB)->create([
            'price' => 100_000,
            'stock' => 20,
            'weight' => 1_000,
        ]);

        CartItem::factory()->for($buyer)->for($productA)->create(['quantity' => 2]);
        CartItem::factory()->for($buyer)->for($productB)->create(['quantity' => 1]);

        $response = $this->postCheckout($buyer, [
            'recipient_name' => 'Pembeli Medan',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1, Medan',
            'city_id' => 278,
            'postal_code' => '20211',
            'payment_method' => 'cod',
            'notes' => 'Tolong dibungkus rapi.',
            'shipping' => [
                $storeA->id => 'jne|REG',
                $storeB->id => 'jne|REG',
            ],
        ]);

        $response->assertRedirect(route('orders.index'));

        $orders = Order::with('items')->where('user_id', $buyer->id)->get();

        $this->assertCount(2, $orders);
        $this->assertEqualsCanonicalizing([$storeA->id, $storeB->id], $orders->pluck('store_id')->all());
        $this->assertDatabaseCount('payment_transactions', 0);

        $orderA = $orders->firstWhere('store_id', $storeA->id);
        $orderB = $orders->firstWhere('store_id', $storeB->id);

        $this->assertSame(100_000, $orderA->subtotal);
        $this->assertSame(2, $orderA->items->sum('quantity'));
        $this->assertSame(100_000, $orderB->subtotal);
        $this->assertSame(1, $orderB->items->sum('quantity'));

        foreach ($orders as $order) {
            $this->assertSame('menunggu', $order->status->value);
            $this->assertSame('cod', $order->payment_method->value);
            $this->assertGreaterThan(0, $order->shipping_cost);
            $this->assertSame($order->subtotal + $order->shipping_cost, $order->total);
            $this->assertSame('Kota Medan', $order->city_name);
            $this->assertSame('Sumatera Utara', $order->province_name);
        }

        $this->assertSame(8, $productA->fresh()->stock);
        $this->assertSame(19, $productB->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_buyer_can_checkout_a_product_directly_without_using_the_cart(): void
    {
        $buyer = User::factory()->buyer()->create();

        $directStore = Store::factory()->create(['city_id' => 278, 'city_name' => 'Kota Medan']);
        $directProduct = Product::factory()->for($directStore)->create([
            'name' => 'Produk Checkout Langsung',
            'price' => 50_000,
            'stock' => 5,
            'weight' => 1_000,
        ]);

        $cartStore = Store::factory()->create(['city_id' => 278, 'city_name' => 'Kota Medan']);
        $cartProduct = Product::factory()->for($cartStore)->create([
            'name' => 'Produk Tetap di Keranjang',
            'price' => 30_000,
            'stock' => 8,
            'weight' => 500,
        ]);
        $cartItem = CartItem::factory()->for($buyer)->for($cartProduct)->create(['quantity' => 1]);

        $this->actingAs($buyer)
            ->post(route('checkout.direct'), [
                'product_id' => $directProduct->id,
                'quantity' => 2,
            ])
            ->assertRedirect(route('checkout.index', [
                'product_id' => $directProduct->id,
                'quantity' => 2,
            ]));

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id]);

        $this->actingAs($buyer)
            ->get(route('checkout.index', [
                'product_id' => $directProduct->id,
                'quantity' => 2,
            ]))
            ->assertOk()
            ->assertSee('Produk Checkout Langsung')
            ->assertDontSee('Produk Tetap di Keranjang');

        $this->postCheckout($buyer, [
            'product_id' => $directProduct->id,
            'quantity' => 2,
            'recipient_name' => 'Pembeli Medan',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1, Medan',
            'city_id' => 278,
            'payment_method' => 'cod',
            'shipping' => [$directStore->id => 'jne|REG'],
        ])->assertRedirect(route('orders.index'))->assertSessionHas('success');

        $order = Order::with('items')->where('user_id', $buyer->id)->firstOrFail();

        $this->assertSame($directStore->id, $order->store_id);
        $this->assertSame(100_000, $order->subtotal);
        $this->assertSame(2, $order->items->sum('quantity'));
        $this->assertSame(3, $directProduct->fresh()->stock);
        $this->assertSame(8, $cartProduct->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id]);
    }

    public function test_checkout_only_processes_selected_cart_items_and_keeps_the_rest(): void
    {
        $buyer = User::factory()->buyer()->create();

        $storeA = Store::factory()->create(['city_id' => 278, 'city_name' => 'Kota Medan']);
        $productA = Product::factory()->for($storeA)->create([
            'name' => 'Produk Terpilih',
            'price' => 50_000,
            'stock' => 5,
            'weight' => 1_000,
        ]);

        $storeB = Store::factory()->create(['city_id' => 278, 'city_name' => 'Kota Medan']);
        $productB = Product::factory()->for($storeB)->create([
            'name' => 'Produk Tidak Terpilih',
            'price' => 80_000,
            'stock' => 7,
            'weight' => 1_000,
        ]);

        $selectedItem = CartItem::factory()->for($buyer)->for($productA)->create(['quantity' => 2]);
        $unselectedItem = CartItem::factory()->for($buyer)->for($productB)->create(['quantity' => 3]);

        $this->actingAs($buyer)
            ->get(route('checkout.index', [
                'cart_selection' => 1,
                'items' => [$selectedItem->id],
            ]))
            ->assertOk()
            ->assertSee('Produk Terpilih')
            ->assertDontSee('Produk Tidak Terpilih');

        $this->postCheckout($buyer, [
            'items' => [$selectedItem->id],
            'recipient_name' => 'Pembeli Medan',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1, Medan',
            'city_id' => 278,
            'payment_method' => 'cod',
            'shipping' => [$storeA->id => 'jne|REG'],
        ])->assertRedirect(route('orders.index'))->assertSessionHas('success');

        $orders = Order::with('items')->where('user_id', $buyer->id)->get();

        $this->assertCount(1, $orders);
        $this->assertSame($storeA->id, $orders->first()->store_id);
        $this->assertSame(100_000, $orders->first()->subtotal);
        $this->assertSame(2, $orders->first()->items->sum('quantity'));
        $this->assertSame(3, $productA->fresh()->stock);
        $this->assertSame(7, $productB->fresh()->stock);

        $this->assertDatabaseMissing('cart_items', ['id' => $selectedItem->id]);
        $this->assertDatabaseHas('cart_items', [
            'id' => $unselectedItem->id,
            'user_id' => $buyer->id,
            'product_id' => $productB->id,
            'quantity' => 3,
        ]);
    }

    public function test_cart_page_lets_the_buyer_choose_items_before_checkout(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = Product::factory()->create(['name' => 'Produk Bisa Dipilih', 'stock' => 5]);

        CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 2]);

        $this->actingAs($buyer)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Produk Bisa Dipilih')
            ->assertSee('name="items[]"', false)
            ->assertSee('name="cart_selection"', false)
            ->assertSee('Checkout item terpilih')
            ->assertSee('Produk yang tidak dicentang tetap tersimpan di keranjang.');
    }

    public function test_checkout_requires_at_least_one_selected_cart_item(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = Product::factory()->create(['stock' => 5]);

        CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 1]);

        $this->actingAs($buyer)
            ->get(route('checkout.index', ['cart_selection' => 1]))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_direct_checkout_rejects_a_product_from_the_sellers_own_store(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $product = Product::factory()->for($store)->create(['stock' => 5]);

        $this->actingAs($seller)
            ->post(route('checkout.direct'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_direct_checkout_rejects_a_quantity_above_stock(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = Product::factory()->create(['stock' => 2]);

        $this->actingAs($buyer)
            ->post(route('checkout.direct'), [
                'product_id' => $product->id,
                'quantity' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_product_pages_offer_both_add_to_cart_and_direct_checkout(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($buyer)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Tambah ke keranjang')
            ->assertSee('Checkout langsung');

        $this->actingAs($buyer)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Checkout langsung');
    }

    public function test_checkout_requires_a_shipping_service_for_every_store(): void
    {
        $buyer = User::factory()->buyer()->create();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create(['stock' => 5]);

        CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 1]);

        $this->postCheckout($buyer, [
            'recipient_name' => 'Pembeli Medan',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1, Medan',
            'city_id' => 278,
            'payment_method' => 'cod',
            'shipping' => [],
        ])->assertSessionHasErrors('shipping');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_checkout_rejects_an_unknown_city(): void
    {
        $buyer = User::factory()->buyer()->create();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create(['stock' => 5]);

        CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 1]);

        $this->postCheckout($buyer, [
            'recipient_name' => 'Pembeli Medan',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1, Medan',
            'city_id' => 999_999,
            'payment_method' => 'cod',
            'shipping' => [$store->id => 'jne|REG'],
        ])->assertSessionHasErrors('city_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_page_displays_cart_groups_and_payment_methods(): void
    {
        $buyer = User::factory()->buyer()->create();
        $storeA = Store::factory()->create(['name' => 'Toko Pertama']);
        $productA = Product::factory()->for($storeA)->create(['name' => 'Produk Pertama']);
        $storeB = Store::factory()->create(['name' => 'Toko Kedua']);
        $productB = Product::factory()->for($storeB)->create(['name' => 'Produk Kedua']);

        CartItem::factory()->for($buyer)->for($productA)->create(['quantity' => 1]);
        CartItem::factory()->for($buyer)->for($productB)->create(['quantity' => 1]);

        $this->actingAs($buyer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Toko Pertama')
            ->assertSee('Toko Kedua')
            ->assertSee('Produk Pertama')
            ->assertSee('Produk Kedua')
            ->assertSee('Transfer Bank')
            ->assertSee('QRIS')
            ->assertSee('COD')
            ->assertSee('Pembayaran manual (simulasi) aktif')
            ->assertDontSee('Pembayaran online belum tersedia');
    }

    public function test_manual_transfer_and_qris_checkout_create_orders_without_midtrans_transaction(): void
    {
        foreach (['transfer', 'qris'] as $paymentMethod) {
            $buyer = User::factory()->buyer()->create();
            $store = Store::factory()->create(['city_id' => 278, 'city_name' => 'Kota Medan']);
            $product = Product::factory()->for($store)->create([
                'price' => 50_000,
                'stock' => 5,
                'weight' => 1_000,
            ]);

            CartItem::factory()->for($buyer)->for($product)->create(['quantity' => 1]);

            $this->postCheckout($buyer, [
                'recipient_name' => 'Pembeli Medan',
                'phone' => '081234567890',
                'address' => 'Jl. Merdeka No. 1, Medan',
                'city_id' => 278,
                'payment_method' => $paymentMethod,
                'shipping' => [$store->id => 'jne|REG'],
            ])->assertRedirect(route('orders.index'))->assertSessionHas('success');

            $order = Order::where('user_id', $buyer->id)->firstOrFail();

            $this->assertSame($paymentMethod, $order->payment_method->value);
            $this->assertSame('menunggu', $order->status->value);
            $this->assertNull($order->payment_transaction_id);
            $this->assertSame(4, $product->fresh()->stock);
        }

        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_page_redirects_when_cart_is_empty(): void
    {
        $buyer = User::factory()->buyer()->create();

        $this->actingAs($buyer)
            ->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');
    }

    public function test_buyer_cannot_update_another_users_cart_item(): void
    {
        $buyer = User::factory()->buyer()->create();
        $otherBuyer = User::factory()->buyer()->create();
        $item = CartItem::factory()->for($otherBuyer)->create(['quantity' => 1]);

        $this->actingAs($buyer)
            ->patch(route('cart.update', $item), ['quantity' => 2])
            ->assertForbidden();
    }

    public function test_buyer_can_remove_an_item_from_the_cart(): void
    {
        $buyer = User::factory()->buyer()->create();
        $item = CartItem::factory()->for($buyer)->create();

        $this->actingAs($buyer)
            ->delete(route('cart.destroy', $item))
            ->assertRedirect();

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    private function postCheckout(User $buyer, array $payload)
    {
        $token = (string) Str::uuid();

        return $this->actingAs($buyer)
            ->withSession(['checkout_token' => $token])
            ->post(route('checkout.store'), $payload + ['checkout_token' => $token]);
    }
}
