<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $categories = Category::withCount(['products' => fn ($query) => $query->active()])
            ->orderBy('name')
            ->get();

        $products = Product::active()
            ->with(['store', 'category'])
            ->latest()
            ->take(8)
            ->get();

        $stores = Store::active()
            ->withCount(['products' => fn ($query) => $query->active()])
            ->latest()
            ->take(4)
            ->get();

        $stats = [
            'stores' => Store::active()->count(),
            'products' => Product::active()->count(),
            'sellers' => User::where('role', 'seller')->where('is_active', true)->count(),
        ];

        return view('home', compact('categories', 'products', 'stores', 'stats'));
    }
}
