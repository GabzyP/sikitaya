<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $sellerCount = User::where('role', 'seller')->where('is_active', true)->count();

        $categoryLinks = [
            ['name' => 'Makanan', 'slug' => 'makanan', 'icon' => 'bi-egg-fried'],
            ['name' => 'Minuman', 'slug' => 'minuman', 'icon' => 'bi-cup-hot'],
            ['name' => 'Pakaian', 'slug' => 'pakaian', 'icon' => 'bi-bag'],
            ['name' => 'Kriya', 'slug' => 'kriya', 'icon' => 'bi-palette'],
            ['name' => 'Oleh-oleh', 'slug' => 'oleh-oleh', 'icon' => 'bi-gift'],
        ];

        return view('home', compact('sellerCount', 'categoryLinks'));
    }
}
