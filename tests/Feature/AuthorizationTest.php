<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_protected_pages(): void
    {
        $this->get(route('cart.index'))->assertRedirect(route('login'));
        $this->get(route('checkout.index'))->assertRedirect(route('login'));
        $this->get(route('orders.index'))->assertRedirect(route('login'));
        $this->get(route('seller.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_buyer_cannot_access_seller_or_admin_pages(): void
    {
        $buyer = User::factory()->buyer()->create();

        $this->actingAs($buyer)->get(route('seller.dashboard'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($buyer)->get(route('seller.reports.index'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_seller_cannot_access_admin_pages(): void
    {
        $seller = User::factory()->seller()->create();
        Store::factory()->for($seller, 'owner')->create();

        $this->actingAs($seller)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.orders.index'))->assertForbidden();
    }

    public function test_seller_without_a_store_is_redirected_to_store_setup(): void
    {
        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertRedirect(route('seller.store.edit'));
    }

    public function test_seller_cannot_manage_another_stores_product(): void
    {
        $seller = User::factory()->seller()->create();
        Store::factory()->for($seller, 'owner')->create();

        $otherStore = Store::factory()->create();
        $otherProduct = Product::factory()->for($otherStore)->create();

        $this->actingAs($seller)
            ->get(route('seller.products.edit', $otherProduct))
            ->assertForbidden();

        $this->actingAs($seller)
            ->delete(route('seller.products.destroy', $otherProduct))
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $otherProduct->id]);
    }

    public function test_seller_can_view_their_own_product_management_pages(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $product = Product::factory()->for($store)->create();

        $this->actingAs($seller)->get(route('seller.products.index'))->assertOk();
        $this->actingAs($seller)->get(route('seller.products.create'))->assertOk();
        $this->actingAs($seller)->get(route('seller.products.edit', $product))->assertOk();
        $this->actingAs($seller)->get(route('seller.store.edit'))->assertOk();
    }

    public function test_seller_cannot_view_another_stores_order(): void
    {
        $seller = User::factory()->seller()->create();
        Store::factory()->for($seller, 'owner')->create();

        $otherStore = Store::factory()->create();
        $order = Order::factory()->for($otherStore)->create();

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $order))
            ->assertForbidden();
    }

    public function test_seller_can_view_their_own_orders(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $order = Order::factory()->for($store)->create();

        $this->actingAs($seller)->get(route('seller.orders.index'))->assertOk();
        $this->actingAs($seller)->get(route('seller.orders.show', $order))->assertOk();
    }

    public function test_buyer_cannot_view_another_buyers_order(): void
    {
        $buyer = User::factory()->buyer()->create();
        $otherBuyer = User::factory()->buyer()->create();
        $order = Order::factory()->for($otherBuyer)->create();

        $this->actingAs($buyer)
            ->get(route('orders.show', $order))
            ->assertForbidden();
    }

    public function test_buyer_can_view_their_own_order(): void
    {
        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->for($buyer)->create();

        $this->actingAs($buyer)
            ->get(route('orders.show', $order))
            ->assertOk();
    }

    public function test_admin_can_access_admin_pages_and_any_order(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.stores.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.categories.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk();
    }
}
