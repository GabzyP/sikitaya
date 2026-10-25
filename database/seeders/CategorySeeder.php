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
            ['name' => 'Kuliner', 'icon' => 'bi-bowl-hot', 'description' => 'Masakan rumahan, lauk, dan camilan khas Medan.'],
            ['name' => 'Minuman', 'icon' => 'bi-cup-hot', 'description' => 'Kopi, teh, dan minuman segar dari UMKM lokal.'],
            ['name' => 'Fashion & Kriya', 'icon' => 'bi-bag-heart', 'description' => 'Tenun, ulos, tas anyaman, dan kriya tangan.'],
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
