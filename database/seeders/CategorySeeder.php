<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Makanan', 'icon' => 'bi-egg-fried', 'description' => 'Masakan rumahan, lauk, dan camilan khas Medan.'],
            ['name' => 'Minuman', 'icon' => 'bi-cup-hot', 'description' => 'Kopi, teh, dan minuman segar dari UMKM lokal.'],
            ['name' => 'Pakaian', 'icon' => 'bi-bag', 'description' => 'Tenun, ulos, dan busana karya perajin lokal.'],
            ['name' => 'Kriya', 'icon' => 'bi-palette', 'description' => 'Anyaman, tekstil, dan kerajinan tangan.'],
            ['name' => 'Oleh-oleh', 'icon' => 'bi-gift', 'description' => 'Bingkisan khas Medan untuk dibawa pulang.'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                [
                    'slug' => Str::slug($category['name']),
                    'icon' => $category['icon'],
                    'description' => $category['description'],
                ]
            );
        }
    }
}
