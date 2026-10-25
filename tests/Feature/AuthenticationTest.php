<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_register_pages_are_available(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Masuk');
        $this->get(route('register'))->assertOk()->assertSee('Daftar');
    }

    public function test_buyer_is_redirected_home_after_login(): void
    {
        $buyer = User::factory()->buyer()->create([
            'email' => 'buyer@example.test',
            'password' => 'password',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $buyer->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($buyer);
    }

    public function test_seller_is_redirected_to_seller_dashboard_after_login(): void
    {
        $seller = User::factory()->seller()->create([
            'email' => 'seller@example.test',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'email' => $seller->email,
            'password' => 'password',
        ])->assertRedirect(route('seller.dashboard'));
    }

    public function test_admin_is_redirected_to_admin_dashboard_after_login(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_is_logged_out_of_protected_pages(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get(route('cart.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_buyer_registration_creates_a_buyer_account(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Pembeli Baru',
            'email' => 'pembeli.baru@example.test',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::Buyer->value,
        ])->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', [
            'email' => 'pembeli.baru@example.test',
            'role' => UserRole::Buyer->value,
        ]);
    }

    public function test_seller_registration_creates_a_store(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Penjual Baru',
            'email' => 'penjual.baru@example.test',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::Seller->value,
            'store_name' => 'Toko Baru Medan',
            'store_description' => 'Produk khas Medan.',
        ])->assertRedirect(route('seller.dashboard'));

        $seller = User::where('email', 'penjual.baru@example.test')->firstOrFail();

        $this->assertSame(UserRole::Seller, $seller->role);
        $this->assertInstanceOf(Store::class, $seller->store);
        $this->assertSame('Toko Baru Medan', $seller->store->name);
        $this->assertSame('Sumatera Utara', $seller->store->province_name);
    }

    public function test_login_requires_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'buyer@example.test',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'email' => 'buyer@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
