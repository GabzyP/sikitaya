<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'store' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:latest,price_asc,price_desc,name'],
        ]);

        $query = Product::active()->with(['store', 'category']);

        if (filled($filters['q'] ?? null)) {
            $q = $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%')
                    ->orWhereHas('store', fn ($store) => $store->where('name', 'like', '%'.$q.'%'));
            });
        }

        if (filled($filters['category'] ?? null)) {
            $query->whereHas('category', fn ($category) => $category->where('slug', $filters['category']));
        }

        if (filled($filters['store'] ?? null)) {
            $query->whereHas('store', fn ($store) => $store->where('slug', $filters['store']));
        }

        match ($filters['sort'] ?? 'latest') {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name' => $query->orderBy('name'),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::withCount(['products' => fn ($builder) => $builder->active()])->orderBy('name')->get();
        $activeCategory = filled($filters['category'] ?? null) ? $categories->firstWhere('slug', $filters['category']) : null;
        $activeStore = filled($filters['store'] ?? null) ? Store::active()->where('slug', $filters['store'])->first() : null;

        return view('products.index', compact('products', 'categories', 'filters', 'activeCategory', 'activeStore'));
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active && $product->store?->is_active, 404);

        $product->load(['store', 'category']);

        $related = Product::active()
            ->with('store')
            ->where('id', '!=', $product->id)
            ->where(function ($query) use ($product) {
                $query->where('category_id', $product->category_id)
                    ->orWhere('store_id', $product->store_id);
            })
            ->take(4)
            ->get();

        return view('products.show', compact('product', 'related'));
    }
}
