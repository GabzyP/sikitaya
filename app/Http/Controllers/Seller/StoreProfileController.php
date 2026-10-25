<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\ShippingException;
use App\Services\ShippingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StoreProfileController extends Controller
{
    public function edit(): View
    {
        $store = auth()->user()->store;
        $shippingError = null;
        $cities = collect();

        try {
            $cities = app(ShippingService::class)->cities();
        } catch (ShippingException $exception) {
            $shippingError = $exception->getMessage();
        }

        return view('seller.store.edit', compact('store', 'cities', 'shippingError'));
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $request->user()->store;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'city_id' => ['required', 'integer'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'logo.image' => 'Logo harus berupa gambar.',
            'logo.mimes' => 'Logo harus berupa JPG, PNG, atau WEBP.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
        ]);

        try {
            $city = app(ShippingService::class)->findCity((int) $data['city_id']);
        } catch (ShippingException $exception) {
            return back()->withInput()->withErrors(['city_id' => $exception->getMessage()]);
        }

        if (! $city) {
            return back()->withInput()->withErrors(['city_id' => 'Kota asal pengiriman tidak ditemukan.']);
        }

        if ($request->hasFile('logo')) {
            if ($store->logo && ! Str::startsWith($store->logo, 'images/')) {
                Storage::disk('public')->delete($store->logo);
            }

            $data['logo'] = $request->file('logo')->store('store-logos', 'public');
        }

        $data['city_name'] = $city['city_name'];
        $data['province_name'] = $city['province'];

        $store->update($data);

        return back()->with('success', 'Profil toko berhasil diperbarui.');
    }
}
