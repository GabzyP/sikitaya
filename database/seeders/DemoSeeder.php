<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@sikitaya.test'],
            [
                'name' => 'Admin Sikitaya',
                'phone' => '081361234567',
                'password' => 'password',
                'role' => UserRole::Admin,
                'is_active' => true,
            ]
        );

        $buyers = collect([
            ['name' => 'Ayu Lestari', 'email' => 'pembeli@sikitaya.test', 'phone' => '081362223344', 'address' => 'Jl. Setia Budi No. 45, Medan Sunggal'],
            ['name' => 'Putri Harahap', 'email' => 'putri@sikitaya.test', 'phone' => '081363334455', 'address' => 'Jl. HM Joni No. 18, Medan Area'],
        ])->mapWithKeys(fn (array $buyer) => [
            $buyer['email'] => User::updateOrCreate(
                ['email' => $buyer['email']],
                $buyer + ['password' => 'password', 'role' => UserRole::Buyer, 'is_active' => true]
            ),
        ]);

        $stores = collect([
            [
                'email' => 'sari@sikitaya.test',
                'owner' => 'Sari Nainggolan',
                'store' => [
                    'name' => 'Dapur Bu Sari',
                    'slug' => 'dapur-bu-sari',
                    'description' => 'Masakan rumahan khas Medan dengan bumbu segar dan resep keluarga. Dimasak dalam jumlah terbatas setiap hari.',
                    'logo' => 'images/stores/dapur-bu-sari.jpg',
                    'phone' => '081364445566',
                    'address' => 'Jl. Jamin Ginting No. 112, Medan Tuntungan',
                ],
            ],
            [
                'email' => 'tanjung@sikitaya.test',
                'owner' => 'Rizky Tanjung',
                'store' => [
                    'name' => 'Kopi Tanjung',
                    'slug' => 'kopi-tanjung',
                    'description' => 'Menyangrai biji kopi Sumatera Utara dalam batch kecil. Arabika Gayo dan Robusta Sidikalang pilihan.',
                    'logo' => 'images/stores/kopi-tanjung.jpg',
                    'phone' => '081365556677',
                    'address' => 'Jl. Sei Deli No. 8, Medan Petisah',
                ],
            ],
            [
                'email' => 'deli@sikitaya.test',
                'owner' => 'Martha Sihombing',
                'store' => [
                    'name' => 'Tenun Deli',
                    'slug' => 'tenun-deli',
                    'description' => 'Kriya tenun dan ulos tangan dari perajin Deli Serdang dan Samosir. Setiap helai dibuat dengan teliti.',
                    'logo' => 'images/stores/tenun-deli.jpg',
                    'phone' => '081366667788',
                    'address' => 'Jl. Sutomo No. 21, Medan Baru',
                ],
            ],
            [
                'email' => 'manisan@sikitaya.test',
                'owner' => 'Lina Siregar',
                'store' => [
                    'name' => 'Manisan Medan',
                    'slug' => 'manisan-medan',
                    'description' => 'Oleh-oleh khas Medan: bika ambon, teri premium, sambal teri, dan gula aren organik.',
                    'logo' => 'images/stores/manisan-medan.jpg',
                    'phone' => '081367778899',
                    'address' => 'Jl. Gatot Subroto No. 77, Medan Kota',
                ],
            ],
        ])->mapWithKeys(function (array $data) {
            $seller = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['owner'],
                    'phone' => $data['store']['phone'],
                    'password' => 'password',
                    'role' => UserRole::Seller,
                    'is_active' => true,
                ]
            );

            $store = Store::updateOrCreate(
                ['user_id' => $seller->id],
                $data['store'] + [
                    'phone' => $data['store']['phone'],
                    'city_id' => 278,
                    'city_name' => 'Kota Medan',
                    'province_name' => 'Sumatera Utara',
                    'is_active' => true,
                ]
            );

            return [$store->slug => $store];
        });

        $categories = Category::pluck('id', 'name');

        $products = [
            [
                'slug' => 'rendang-dapur-bu-sari', 'store' => 'dapur-bu-sari', 'category' => 'Makanan',
                'name' => 'Rendang Dapur Bu Sari', 'price' => 45000, 'stock' => 24, 'weight' => 500,
                'description' => 'Rendang daging sapi empuk dengan rempah kering khas Minang, dimasak perlahan selama enam jam. Kemasan 400 gram siap hangatkan.',
            ],
            [
                'slug' => 'sambal-andaliman', 'store' => 'dapur-bu-sari', 'category' => 'Makanan',
                'name' => 'Sambal Andaliman', 'price' => 28000, 'stock' => 40, 'weight' => 250,
                'description' => 'Sambal segar dengan andaliman khas Sumatera Utara yang pedas dan wangi. Cocok untuk ikan bakar dan nasi hangat.',
            ],
            [
                'slug' => 'bika-ambon-keju', 'store' => 'dapur-bu-sari', 'category' => 'Makanan',
                'name' => 'Bika Ambon Keju Spesial', 'price' => 55000, 'stock' => 15, 'weight' => 700,
                'description' => 'Bika ambon lembut dengan topping keju melimpah. Dipanggang fresh setiap pagi dengan santan pilihan.',
            ],
            [
                'slug' => 'keripik-singkong-balado', 'store' => 'dapur-bu-sari', 'category' => 'Makanan',
                'name' => 'Keripik Singkong Balado', 'price' => 22000, 'stock' => 50, 'weight' => 300,
                'description' => 'Keripik singkong renyah dengan bumbu balado pedas manis. Camilan favorit untuk menemani waktu santai.',
            ],
            [
                'slug' => 'kopi-arabika-gayo', 'store' => 'kopi-tanjung', 'category' => 'Minuman',
                'name' => 'Kopi Arabika Gayo 250g', 'price' => 68000, 'stock' => 30, 'weight' => 300,
                'description' => 'Biji arabika Gayo dengan profil rasa cokelat, jeruk, dan keasaman lembut. Disangrai medium dan dikemas 250 gram.',
            ],
            [
                'slug' => 'kopi-robusta-sidikalang', 'store' => 'kopi-tanjung', 'category' => 'Minuman',
                'name' => 'Kopi Robusta Sidikalang 250g', 'price' => 55000, 'stock' => 35, 'weight' => 300,
                'description' => 'Robusta Sidikalang dengan body tebal dan aroma kuat. Pilihan tepat untuk espresso dan kopi susu.',
            ],
            [
                'slug' => 'kopi-susu-gula-aren', 'store' => 'kopi-tanjung', 'category' => 'Minuman',
                'name' => 'Kopi Susu Gula Aren 1L', 'price' => 35000, 'stock' => 18, 'weight' => 1000,
                'description' => 'Kopi susu siap minum dengan gula aren asli. Kemasan satu liter, cukup untuk empat gelas.',
            ],
            [
                'slug' => 'cold-brew-botol', 'store' => 'kopi-tanjung', 'category' => 'Minuman',
                'name' => 'Cold Brew Botol 250ml', 'price' => 28000, 'stock' => 25, 'weight' => 300,
                'description' => 'Cold brew dari arabika Gayo yang diekstraksi dingin selama 18 jam. Segar, rendah asam, dan tanpa gula.',
            ],
            [
                'slug' => 'ulos-sadum', 'store' => 'tenun-deli', 'category' => 'Pakaian',
                'name' => 'Ulos Sadum Tenun Tangan', 'price' => 385000, 'stock' => 6, 'weight' => 500,
                'description' => 'Ulos sadum tenun tangan dengan motif dan warna khas Batak. Ditenun oleh perajin di Deli Serdang.',
            ],
            [
                'slug' => 'kain-tenun-meski', 'store' => 'tenun-deli', 'category' => 'Kriya',
                'name' => 'Kain Tenun Motif Meski', 'price' => 275000, 'stock' => 8, 'weight' => 450,
                'description' => 'Kain tenun bermotif meski dengan pewarna alam. Panjang 2 meter, cocok untuk busana dan dekorasi.',
            ],
            [
                'slug' => 'tas-anyaman-pandan', 'store' => 'tenun-deli', 'category' => 'Kriya',
                'name' => 'Tas Anyaman Pandan', 'price' => 125000, 'stock' => 12, 'weight' => 400,
                'description' => 'Tas anyaman pandan buatan tangan dengan lining kain tenun. Ringan, kuat, dan ramah lingkungan.',
            ],
            [
                'slug' => 'selendang-tenun-deli', 'store' => 'tenun-deli', 'category' => 'Pakaian',
                'name' => 'Selendang Tenun Deli', 'price' => 185000, 'stock' => 10, 'weight' => 300,
                'description' => 'Selendang tenun dengan warna hangat dan motif geometris. Nyaman dipakai untuk acara formal maupun kasual.',
            ],
            [
                'slug' => 'bika-ambon-original', 'store' => 'manisan-medan', 'category' => 'Oleh-oleh',
                'name' => 'Bika Ambon Original', 'price' => 48000, 'stock' => 20, 'weight' => 600,
                'description' => 'Bika ambon original dengan tekstur bersarang dan aroma daun jeruk. Dikemas rapi untuk oleh-oleh.',
            ],
            [
                'slug' => 'teri-medan-premium', 'store' => 'manisan-medan', 'category' => 'Oleh-oleh',
                'name' => 'Teri Medan Premium 250g', 'price' => 42000, 'stock' => 30, 'weight' => 300,
                'description' => 'Ikan teri Medan kering pilihan, bersih, dan tidak too salty. Dikemas dalam kantong ziplock 250 gram.',
            ],
            [
                'slug' => 'sambal-ikan-teri', 'store' => 'manisan-medan', 'category' => 'Oleh-oleh',
                'name' => 'Sambal Ikan Teri', 'price' => 32000, 'stock' => 25, 'weight' => 250,
                'description' => 'Sambal teri pedas gurih dengan campuran cabai merah dan bawang. Praktis disantap dengan nasi panas.',
            ],
            [
                'slug' => 'gula-aren-organik', 'store' => 'manisan-medan', 'category' => 'Oleh-oleh',
                'name' => 'Gula Aren Organik', 'price' => 30000, 'stock' => 22, 'weight' => 500,
                'description' => 'Gula aren organik dari petani Sumatera Utara. Manis alami dengan aroma karamel, tanpa pemanis tambahan.',
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['slug' => $product['slug']],
                [
                    'store_id' => $stores[$product['store']]->id,
                    'category_id' => $categories[$product['category']],
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'stock' => $product['stock'],
                    'weight' => $product['weight'],
                    'image' => 'images/products/'.$product['slug'].'.jpg',
                    'is_active' => true,
                ]
            );
        }

        if (Order::where('order_number', 'like', 'SKT-DEMO-%')->exists()) {
            return;
        }

        $buyerAyu = $buyers['pembeli@sikitaya.test'];
        $buyerPutri = $buyers['putri@sikitaya.test'];

        $this->createOrder('SKT-DEMO-0001', $buyerAyu, $stores['dapur-bu-sari'], [
            ['slug' => 'rendang-dapur-bu-sari', 'quantity' => 1],
            ['slug' => 'sambal-andaliman', 'quantity' => 2],
        ], OrderStatus::Selesai, PaymentMethod::Transfer, 18, 10000, [
            'paid_at' => now()->subDays(17), 'shipped_at' => now()->subDays(16), 'completed_at' => now()->subDays(13),
            'tracking_number' => 'JNE1029384756', 'payment_proof' => 'payment-proofs/demo-qris.png',
        ]);

        $this->createOrder('SKT-DEMO-0002', $buyerPutri, $stores['kopi-tanjung'], [
            ['slug' => 'kopi-arabika-gayo', 'quantity' => 2],
            ['slug' => 'cold-brew-botol', 'quantity' => 1],
        ], OrderStatus::Dikirim, PaymentMethod::Qris, 4, 12000, [
            'paid_at' => now()->subDays(3), 'shipped_at' => now()->subDays(2),
            'tracking_number' => 'JNE8877665544', 'payment_proof' => 'payment-proofs/demo-qris.png',
        ]);

        $this->createOrder('SKT-DEMO-0003', $buyerAyu, $stores['tenun-deli'], [
            ['slug' => 'ulos-sadum', 'quantity' => 1],
        ], OrderStatus::Dibayar, PaymentMethod::Transfer, 7, 10000, [
            'paid_at' => now()->subDays(6), 'payment_proof' => 'payment-proofs/demo-qris.png',
        ]);

        $this->createOrder('SKT-DEMO-0004', $buyerPutri, $stores['manisan-medan'], [
            ['slug' => 'teri-medan-premium', 'quantity' => 2],
            ['slug' => 'gula-aren-organik', 'quantity' => 1],
        ], OrderStatus::Menunggu, PaymentMethod::Transfer, 2, 10000, [
            'payment_proof' => 'payment-proofs/demo-qris.png',
            'payment_proof_uploaded_at' => now()->subDays(1),
        ]);

        $this->createOrder('SKT-DEMO-0005', $buyerAyu, $stores['dapur-bu-sari'], [
            ['slug' => 'keripik-singkong-balado', 'quantity' => 3],
        ], OrderStatus::Menunggu, PaymentMethod::Cod, 1, 10000);

        $this->createOrder('SKT-DEMO-0006', $buyerPutri, $stores['kopi-tanjung'], [
            ['slug' => 'kopi-susu-gula-aren', 'quantity' => 1],
        ], OrderStatus::Selesai, PaymentMethod::Cod, 12, 10000, [
            'paid_at' => now()->subDays(8), 'shipped_at' => now()->subDays(9), 'completed_at' => now()->subDays(8),
            'tracking_number' => 'COD-88990011',
        ]);

        $this->createOrder('SKT-DEMO-0007', $buyerAyu, $stores['tenun-deli'], [
            ['slug' => 'tas-anyaman-pandan', 'quantity' => 1],
            ['slug' => 'selendang-tenun-deli', 'quantity' => 1],
        ], OrderStatus::Selesai, PaymentMethod::Transfer, 15, 12000, [
            'paid_at' => now()->subDays(14), 'shipped_at' => now()->subDays(13), 'completed_at' => now()->subDays(10),
            'tracking_number' => 'POS2233445566', 'payment_proof' => 'payment-proofs/demo-qris.png',
        ]);

        $this->createOrder('SKT-DEMO-0008', $buyerPutri, $stores['manisan-medan'], [
            ['slug' => 'bika-ambon-original', 'quantity' => 1],
        ], OrderStatus::Dikirim, PaymentMethod::Cod, 5, 10000, [
            'shipped_at' => now()->subDays(3), 'tracking_number' => 'COD-11223344',
        ]);
    }

    /**
     * @param  list<array{slug:string,quantity:int}>  $items
     * @param  array<string, mixed>  $extra
     */
    private function createOrder(
        string $number,
        User $buyer,
        Store $store,
        array $items,
        OrderStatus $status,
        PaymentMethod $paymentMethod,
        int $daysAgo,
        int $shippingCost,
        array $extra = [],
    ): void {
        $createdAt = now()->subDays($daysAgo)->setTime(10, 30);
        $subtotal = 0;

        foreach ($items as $item) {
            $product = Product::where('slug', $item['slug'])->firstOrFail();
            $subtotal += $product->price * $item['quantity'];
        }

        $order = new Order([
            'order_number' => $number,
            'user_id' => $buyer->id,
            'store_id' => $store->id,
            'recipient_name' => $buyer->name,
            'phone' => $buyer->phone,
            'address' => $buyer->address,
            'city_id' => 278,
            'city_name' => 'Kota Medan',
            'province_name' => 'Sumatera Utara',
            'postal_code' => '20228',
            'courier' => 'jne',
            'shipping_service' => 'REG',
            'shipping_etd' => '2-3 hari',
            'shipping_cost' => $shippingCost,
            'subtotal' => $subtotal,
            'total' => $subtotal + $shippingCost,
            'payment_method' => $paymentMethod->value,
            'status' => $status->value,
            'notes' => 'Pesanan demo untuk kebutuhan presentasi.',
        ]);

        $order->forceFill($extra);
        $order->created_at = $createdAt;
        $order->updated_at = $createdAt;
        $order->save();

        foreach ($items as $item) {
            $product = Product::where('slug', $item['slug'])->firstOrFail();

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_image' => $product->image,
                'product_price' => $product->price,
                'quantity' => $item['quantity'],
                'subtotal' => $product->price * $item['quantity'],
                'weight' => $product->weight,
            ]);
        }
    }
}
