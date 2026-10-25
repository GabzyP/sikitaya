<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in([UserRole::Buyer->value, UserRole::Seller->value])],
            'store_name' => ['nullable', 'required_if:role,'.UserRole::Seller->value, 'string', 'max:100'],
            'store_description' => ['nullable', 'string', 'max:500'],
        ]);

        $role = UserRole::from($data['role']);

        $user = DB::transaction(function () use ($data, $role) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => $role,
                'is_active' => true,
            ]);

            if ($role === UserRole::Seller) {
                Store::create([
                    'user_id' => $user->id,
                    'name' => $data['store_name'],
                    'slug' => Store::generateUniqueSlug($data['store_name']),
                    'description' => $data['store_description'] ?? 'Toko UMKM Kota Medan.',
                    'phone' => $data['phone'] ?? null,
                    'city_id' => 278,
                    'city_name' => 'Kota Medan',
                    'province_name' => 'Sumatera Utara',
                    'is_active' => true,
                ]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        $message = $role === UserRole::Seller
            ? 'Akun penjual berhasil dibuat. Lengkapi profil toko Anda untuk mulai berjualan.'
            : 'Akun berhasil dibuat. Selamat berbelanja produk UMKM Kota Medan!';

        return redirect()->to($user->dashboardRoute())->with('success', $message);
    }
}
