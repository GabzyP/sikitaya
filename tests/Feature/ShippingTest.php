<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Services\ShippingException;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dummy_city_dataset_contains_medan(): void
    {
        $cities = app(ShippingService::class)->cities();
        $medan = $cities->firstWhere('id', 278);

        $this->assertNotNull($medan);
        $this->assertSame('Kota Medan', $medan['city_name']);
        $this->assertSame('Sumatera Utara', $medan['province']);
    }

    public function test_dummy_costs_are_deterministic_and_available_for_every_courier(): void
    {
        $shipping = app(ShippingService::class);

        $options = $shipping->options(278, 278, 1_000);
        $this->assertNotEmpty($options);

        $services = collect($options)->pluck('service');
        $this->assertContains('REG', $services);
        $this->assertContains('Reguler', $services);
        $this->assertSame(
            collect($options)->firstWhere('courier', 'jne')['cost'],
            $shipping->cost(278, 278, 1_000, 'jne')[0]['cost']
        );

        $this->assertSame(7_200, $shipping->cost(278, 278, 1_000, 'jne')[0]['cost']);
        $this->assertSame(14_400, $shipping->cost(278, 278, 2_000, 'jne')[0]['cost']);
    }

    public function test_unsupported_courier_is_rejected(): void
    {
        $this->expectException(ShippingException::class);

        app(ShippingService::class)->cost(278, 278, 1_000, 'unknown');
    }

    public function test_shipping_cost_endpoint_returns_dummy_options(): void
    {
        $store = Store::factory()->create(['city_id' => 278, 'city_name' => 'Kota Medan']);

        $this->getJson(route('shipping.cost', [
            'store_id' => $store->id,
            'destination' => 278,
            'weight' => 1_000,
        ]))
            ->assertOk()
            ->assertJsonPath('mode', 'dummy')
            ->assertJsonStructure([
                'mode',
                'origin',
                'options' => [
                    ['courier', 'courier_name', 'service', 'description', 'cost', 'etd'],
                ],
            ]);
    }

    public function test_shipping_cost_endpoint_rejects_inactive_stores(): void
    {
        $store = Store::factory()->inactive()->create();

        $this->getJson(route('shipping.cost', [
            'store_id' => $store->id,
            'destination' => 278,
            'weight' => 1_000,
        ]))->assertUnprocessable();
    }
}
