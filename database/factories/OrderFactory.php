<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(20, 500) * 1000;
        $shippingCost = 9000;

        return [
            'order_number' => 'SKT-TEST-'.fake()->unique()->numerify('######'),
            'user_id' => User::factory()->buyer(),
            'store_id' => Store::factory(),
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->streetAddress(),
            'city_id' => 278,
            'city_name' => 'Kota Medan',
            'province_name' => 'Sumatera Utara',
            'postal_code' => '20211',
            'courier' => 'jne',
            'shipping_service' => 'REG',
            'shipping_etd' => '2-3 hari',
            'shipping_cost' => $shippingCost,
            'subtotal' => $subtotal,
            'total' => $subtotal + $shippingCost,
            'payment_method' => PaymentMethod::Transfer,
            'status' => OrderStatus::Menunggu,
            'notes' => null,
        ];
    }

    public function cod(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Cod,
            'paid_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Dibayar,
            'paid_at' => now(),
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Dikirim,
            'paid_at' => now(),
            'shipped_at' => now(),
            'tracking_number' => 'JNE'.fake()->numerify('##########'),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Selesai,
            'paid_at' => now(),
            'shipped_at' => now(),
            'completed_at' => now(),
            'tracking_number' => 'JNE'.fake()->numerify('##########'),
        ]);
    }

    public function withProof(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_proof' => 'payment-proofs/proof.png',
            'payment_proof_uploaded_at' => now(),
        ]);
    }
}
