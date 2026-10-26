<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Store::active()
            ->withCount(['products' => fn ($query) => $query->active()])
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters) {
                $q = $filters['q'];

                $query->where(function ($builder) use ($q) {
                    $builder->where('name', 'like', '%'.$q.'%')
                        ->orWhere('description', 'like', '%'.$q.'%')
                        ->orWhere('city_name', 'like', '%'.$q.'%');
                });
            });

        $stores = ($request->user()
            ? $query->orderBy('name')->paginate(9)
            : $query->latest()->paginate(6))
            ->withQueryString();

        return view('stores.index', compact('stores'));
    }

    public function show(Store $store): View
    {
        abort_unless($store->is_active, 404);

        $products = Product::active()
            ->with('category')
            ->where('store_id', $store->id)
            ->latest()
            ->paginate(12);

        $store->loadCount(['products' => fn ($query) => $query->active()]);

        return view('stores.show', compact('store', 'products'));
    }
}
