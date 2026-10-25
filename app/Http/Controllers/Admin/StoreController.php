<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $stores = Store::query()
            ->with('owner')
            ->withCount(['products', 'orders'])
            ->when($filters['q'] ?? null, function ($query, $q) {
                $query->where(function ($builder) use ($q) {
                    $builder->where('name', 'like', '%'.$q.'%')
                        ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', '%'.$q.'%'));
                });
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.stores.index', compact('stores', 'filters'));
    }

    public function toggle(Store $store): RedirectResponse
    {
        if (! $store->is_active && ! $store->owner?->is_active) {
            return back()->with('error', 'Aktifkan kembali akun penjual terlebih dahulu sebelum mengaktifkan toko.');
        }

        $store->update(['is_active' => ! $store->is_active]);

        return back()->with('success', 'Toko '.$store->name.' berhasil '.($store->is_active ? 'diaktifkan' : 'dinonaktifkan').'.');
    }
}
