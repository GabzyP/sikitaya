<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_returns_a_successful_response(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_the_storefront_lists_active_products(): void
    {
        $store = Store::factory()->create(['name' => 'Toko Uji Medan']);
        Product::factory()->for($store)->create(['name' => 'Kopi Sidikalang']);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Kopi Sidikalang');

        $this->get(route('stores.show', $store))
            ->assertOk()
            ->assertSee('Toko Uji Medan');
    }

    public function test_product_detail_page_shows_the_product(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create([
            'name' => 'Kopi Sidikalang',
            'description' => 'Kopi arabika khas Sumatera Utara.',
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Kopi Sidikalang')
            ->assertSee('Kopi arabika khas Sumatera Utara.');
    }

    public function test_catalog_search_filters_products_by_name(): void
    {
        $store = Store::factory()->create();
        Product::factory()->for($store)->create(['name' => 'Kopi Sidikalang']);
        Product::factory()->for($store)->create(['name' => 'Ulosh Batak']);

        $this->get(route('products.index', ['q' => 'Kopi']))
            ->assertOk()
            ->assertSee('Kopi Sidikalang')
            ->assertDontSee('Ulosh Batak');
    }

    public function test_inactive_products_are_hidden_from_the_catalog(): void
    {
        $store = Store::factory()->create();
        Product::factory()->for($store)->create([
            'name' => 'Produk Nonaktif',
            'is_active' => false,
        ]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('Produk Nonaktif');
    }

    public function test_products_from_inactive_stores_are_hidden(): void
    {
        $store = Store::factory()->inactive()->create();
        Product::factory()->for($store)->create(['name' => 'Produk Toko Tutup']);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('Produk Toko Tutup');
    }
}
