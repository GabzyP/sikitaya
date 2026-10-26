# Sikitaya — Marketplace UMKM Kota Medan

Sikitaya adalah aplikasi web marketplace multi-vendor untuk UMKM Kota Medan. Pembeli dapat menjelajahi katalog, checkout langsung dari produk, atau memasukkan produk dari beberapa toko ke keranjang dan memilih item yang akan dibeli. Setelah itu pembeli memilih ongkir per toko dan menyelesaikan pembayaran melalui transfer bank/QRIS simulasi, COD, atau Midtrans bila integrasinya diaktifkan. Penjual mengelola toko, produk, pesanan, dan laporan penjualan; admin mengelola pengguna, toko, kategori, pesanan, pembayaran, alokasi dana, dan laporan platform.

Checkout baru saat ini menawarkan **Transfer Bank (simulasi)**, **QRIS (simulasi)**, dan **COD**. Kode, rute, halaman admin, serta dokumentasi Midtrans tetap disimpan, tetapi opsi Midtrans tidak ditampilkan sampai `MIDTRANS_CHECKOUT_ENABLED=true` dan prasyarat split/disbursement tersedia.

## Teknologi

- Laravel 12 (PHP >= 8.2; diuji dengan PHP 8.4)
- MySQL 8
- Bootstrap 5 + Bootstrap Icons (CDN), CSS/JS kustom di `public/`
- RajaOngkir Starter, dengan mode dummy bawaan agar aplikasi tetap bisa dijalankan tanpa API key
- Midtrans Snap, Get Status API, Partner transaction split, dan/atau Iris disbursement
- `maatwebsite/excel` 4 untuk ekspor Excel
- `barryvdh/laravel-dompdf` 3 untuk ekspor PDF
- PHPUnit 11 untuk pengujian

## Fitur Utama

### Publik

- Beranda dengan kategori, toko, dan produk pilihan
- Katalog produk dengan pencarian, filter kategori, dan pengurutan
- Detail produk dan halaman toko publik
- Perhitungan ongkir melalui endpoint `GET /api/ongkir`

### Pembeli

- Registrasi dan login (akun wajib untuk bertransaksi)
- Checkout langsung dari kartu produk atau halaman detail tanpa memasukkan produk ke keranjang
- Keranjang belanja per produk dengan pilihan item; hanya item yang dicentang yang diproses dan sisanya tetap tersimpan
- Checkout satu kali untuk produk dari beberapa toko
- Pemilihan layanan ongkir terpisah untuk setiap toko
- Pembayaran transfer bank/QRIS simulasi dengan unggah bukti, COD, atau Midtrans bila diaktifkan
- Halaman status pembayaran gabungan Midtrans, sinkronisasi status, dan percobaan pembayaran ulang saat integrasi aktif
- Riwayat pesanan, detail pesanan, dan konfirmasi pesanan selesai
- Pesanan transfer/QRIS baru maupun lama tetap dapat dilihat dan diverifikasi pada riwayat

### Penjual

- Profil toko (nama, deskripsi, logo, alamat, kota asal ongkir)
- CRUD produk, stok, harga, berat, gambar, dan status aktif
- Daftar pesanan masuk dan detail pesanan
- Melihat status pembayaran Midtrans dan alokasi dana toko secara read-only bila integrasi aktif
- Konfirmasi pembayaran transfer/QRIS simulasi melalui bukti bayar, konfirmasi COD, dan input nomor resi
- Laporan penjualan dengan filter tanggal dan status, ekspor Excel/PDF

### Admin

- Dashboard ringkasan platform
- Aktivasi/nonaktivasi pengguna dan toko
- CRUD kategori
- Daftar dan detail seluruh pesanan
- Manajemen pembayaran gabungan Midtrans, alokasi dana per toko, dan status pencairan
- Pengaturan merchant ID Partner Midtrans dan rekening penerima per toko
- Validasi rekening penerima ke API disbursement
- Laporan penjualan platform dengan filter tanggal, status, dan toko; ekspor Excel/PDF

## Alur Pesanan

Status pesanan: **menunggu → dibayar → dikirim → selesai**.

| Metode | Alur |
| --- | --- |
| Midtrans (opsional, hanya jika diaktifkan) | Pembeli membuat pesanan (`menunggu`) dan satu transaksi gabungan → diarahkan ke halaman pembayaran Midtrans → notifikasi `settlement`/`capture` accepted memverifikasi signature, nominal, dan ID → seluruh pesanan terkait menjadi `dibayar` secara atomik → penjual memasukkan resi (`dikirim`) → pembeli mengonfirmasi diterima (`selesai`) |
| Transfer bank / QRIS (simulasi) | Pembeli membuat pesanan (`menunggu`) → pembeli melakukan pembayaran simulasi dan mengunggah bukti bayar → penjual memverifikasi bukti (`dibayar`) → penjual memasukkan resi (`dikirim`) → pembeli mengonfirmasi diterima (`selesai`). Tidak ada transaksi Midtrans. |
| COD | Pembeli membuat pesanan (`menunggu`) → penjual mengonfirmasi pesanan (`dibayar`, `paid_at` masih kosong) → penjual mengirim (`dikirim`) → pembeli mengonfirmasi diterima (`selesai`, `paid_at` diisi). Tidak ada transaksi Midtrans maupun bukti bayar. |

Satu checkout dapat berisi produk dari beberapa toko. Karena ongkir dan penjual berbeda, aplikasi membuat **satu pesanan per toko**. Untuk Midtrans, semua pesanan tersebut dihubungkan ke **satu transaksi pembayaran gabungan** dengan nominal sama dengan jumlah seluruh `total` pesanan, termasuk ongkir masing-masing toko.

Alokasi dana awal setiap toko sama dengan total pesanannya. Komisi platform bernilai **0** pada implementasi ini. Biaya Midtrans ditanggung platform dan ditampilkan secara transparan; biaya tersebut tidak mengurangi dana penjual. Jalur alokasi dipilih otomatis:

- **Partner transaction split** dipakai lebih dulu jika aktif dan semua toko memiliki merchant ID terdaftar. Alokasi split diselesaikan otomatis setelah settlement.
- **Disbursement API (Iris)** dipakai sebagai fallback jika split tidak tersedia tetapi rekening penerima toko sudah terverifikasi. Admin harus menyetujui alokasi, lalu status pencairan hanya boleh berasal dari respons Midtrans.
- Jika keduanya belum tersedia, opsi Midtrans tidak ditawarkan dan checkout tetap memakai transfer/QRIS simulasi atau COD; admin tidak boleh menandai dana Midtrans sudah dikirim secara manual.

## Akun Demo

Jalankan seeder terlebih dahulu, lalu masuk dengan akun berikut (semua password: `password`).

| Peran | Email | Keterangan |
| --- | --- | --- |
| Admin | `admin@sikitaya.test` | Mengelola seluruh platform |
| Penjual | `sari@sikitaya.test` | Dapur Bu Sari |
| Penjual | `tanjung@sikitaya.test` | Kopi Tanjung |
| Penjual | `deli@sikitaya.test` | Tenun Deli |
| Penjual | `manisan@sikitaya.test` | Manisan Medan |
| Pembeli | `pembeli@sikitaya.test` | Ayu Lestari, memiliki beberapa pesanan demo |
| Pembeli | `putri@sikitaya.test` | Putri Harahap, memiliki beberapa pesanan demo |

Seeder juga membuat 4 kategori, 4 toko, 16 produk, dan 8 pesanan demo (`SKT-DEMO-0001` sampai `SKT-DEMO-0008`) dengan variasi status dan metode pembayaran.

## Instalasi

Prasyarat: PHP >= 8.2 dengan ekstensi `pdo_mysql`, `fileinfo`, `gd`, `zip`, dan `mbstring`; Composer 2; MySQL 8.

1. Pasang dependensi:

   ```bash
   composer install
   ```

2. Siapkan environment:

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

3. Buat database MySQL:

   ```sql
   CREATE DATABASE sikitaya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. Sesuaikan koneksi database di `.env`:

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=sikitaya
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. Jalankan migrasi dan seeder:

   ```bash
   php artisan migrate --seed
   ```

6. Buat symlink penyimpanan publik (logo, foto produk, bukti bayar):

   ```bash
   php artisan storage:link
   ```

7. Jalankan server:

   ```bash
   php artisan serve
   ```

   Buka `http://127.0.0.1:8000`.

Tidak ada langkah build frontend. Bootstrap, ikon, dan aset kustom dimuat langsung dari CDN atau folder `public/`.

## Konfigurasi RajaOngkir

### Mode dummy (bawaan)

```dotenv
RAJAONGKIR_MODE=dummy
```

- Tidak memerlukan API key maupun koneksi internet.
- Memakai 501 kota dari `database/data/rajaongkir_cities.json`.
- Tarif dihitung deterministik berdasarkan zona provinsi, berat yang dibulatkan ke kg, dan kurir JNE/POS/TIKI.
- Cocok untuk demo, pengembangan, dan pengujian otomatis.
- Endpoint yang dipakai aplikasi: `GET /api/ongkir?store_id={id}&destination={city_id}&weight={gram}`.

### Mode API RajaOngkir Starter

```dotenv
RAJAONGKIR_MODE=api
RAJAONGKIR_API_KEY=isi_api_key_anda
RAJAONGKIR_BASE_URL=https://api.rajaongkir.com/starter
RAJAONGKIR_TIMEOUT=15
```

- Daftar kota di-cache selama satu hari.
- Ongkir dihitung dari `city_id` toko asal ke kota tujuan pembeli.
- Jika API gagal atau API key kosong, aplikasi menampilkan pesan kesalahan yang jelas, bukan error mentah.
- RajaOngkir Starter umumnya mendukung JNE, POS Indonesia, dan TIKI. Kode kurir dikonfigurasi di `config/rajaongkir.php`.

Kota asal default toko baru adalah `278` (Kota Medan, Sumatera Utara). Penjual dapat mengubahnya melalui menu **Profil Toko**.

## Konfigurasi Midtrans (disimpan, nonaktif secara bawaan)

Kode integrasi Midtrans dipertahankan untuk aktivasi di masa depan. Opsi Midtrans di checkout hanya muncul jika `MIDTRANS_CHECKOUT_ENABLED=true` **dan** prasyarat split atau disbursement sudah siap. Tanpa itu, checkout tetap memakai transfer/QRIS simulasi dan COD.

### Prasyarat akun dan kapabilitas

1. Akun Midtrans Sandbox dengan **Server Key** dan **Client Key**.
2. Akses **Partner Solutions transaction split** untuk akun Sikitaya, beserta merchant ID platform.
3. Setiap toko memiliki merchant ID yang terdaftar di bawah Partner Sikitaya; **atau**
4. Akses **Disbursement API (Iris)** beserta Creator Key/Approver Key dan rekening toko yang sudah divalidasi.

Akses split dan disbursement tidak dapat dibuat dari kode aplikasi. Jika keduanya belum diberikan Midtrans, opsi Midtrans disembunyikan, sedangkan transfer/QRIS simulasi dan COD tetap berjalan. Aplikasi tidak menggunakan pencairan manual sebagai pengganti. Untuk callback nyata, Midtrans juga memerlukan URL publik (HTTPS/tunnel); Midtrans tidak dapat memanggil `localhost`.

### Environment

```dotenv
MIDTRANS_ENABLED=true
MIDTRANS_CHECKOUT_ENABLED=false
MIDTRANS_ENVIRONMENT=sandbox
MIDTRANS_PRODUCTION_ENABLED=false
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_PLATFORM_MERCHANT_ID=
MIDTRANS_NOTIFICATION_URL=
MIDTRANS_EXPIRY_MINUTES=1440

MIDTRANS_SPLIT_ENABLED=false
MIDTRANS_DISBURSEMENT_ENABLED=false
MIDTRANS_IRIS_BASE_URL=
MIDTRANS_IRIS_CREATOR_KEY=
MIDTRANS_IRIS_APPROVER_KEY=
MIDTRANS_IRIS_AUTO_APPROVE=true
MIDTRANS_IRIS_DEFAULT_EMAIL=
MIDTRANS_IRIS_TIMEOUT=30
```

- `MIDTRANS_CHECKOUT_ENABLED=false` adalah kondisi bawaan: kode dan halaman admin Midtrans tetap ada, tetapi metode Midtrans tidak ditawarkan di checkout dan POST langsung ke metode tersebut ditolak.
- `MIDTRANS_ENABLED` mengaktifkan integrasi secara umum. Untuk menawarkan Midtrans di checkout, kedua flag harus aktif dan prasyarat split/disbursement harus siap.
- Mode Sandbox adalah bawaan. Mode production hanya aktif jika `MIDTRANS_ENVIRONMENT=production` **dan** `MIDTRANS_PRODUCTION_ENABLED=true`. Mengisi key production saja tidak cukup.
- `MIDTRANS_SERVER_KEY` tidak pernah dikirim ke browser. `MIDTRANS_CLIENT_KEY` disimpan untuk konfigurasi paket Midtrans, bukan untuk rendering rahasia.
- Jangan commit nilai key. File `.env` tidak boleh dimasukkan ke repositori.
- Kosongkan `MIDTRANS_IRIS_BASE_URL` untuk memakai URL Sandbox/Production bawaan, atau isi jika Midtrans memberikan endpoint khusus.
- Jika `MIDTRANS_IRIS_AUTO_APPROVE=true` dan approver key tersedia, aplikasi memanggil endpoint approval Iris setelah admin mengirim alokasi. Jika `false`, approval dilakukan melalui dashboard Iris; alokasi tetap berstatus `processing` sampai Midtrans mengonfirmasi.

### Langkah setup

1. Jalankan migrasi baru:

   ```bash
   php artisan migrate
   ```

2. Isi key Sandbox, platform merchant ID, dan pilih minimal satu jalur:

   ```dotenv
   MIDTRANS_SPLIT_ENABLED=true
   # atau, bila split tidak tersedia:
   MIDTRANS_DISBURSEMENT_ENABLED=true
   MIDTRANS_IRIS_CREATOR_KEY=...
   MIDTRANS_IRIS_APPROVER_KEY=...
   ```

3. Setelah key, split/disbursement, dan kesiapan toko terverifikasi, aktifkan opsi checkout:

   ```dotenv
   MIDTRANS_CHECKOUT_ENABLED=true
   ```

4. Atur notification URL di dashboard Midtrans ke:

   ```text
   https://domain-anda.example/midtrans/notification
   ```

   Atau isi `MIDTRANS_NOTIFICATION_URL` dengan URL yang sama. Route ini dikecualikan dari CSRF, tidak memerlukan login pembeli, dan hanya menerima POST.

5. Untuk split: buka **Admin → Pembayaran → Kesiapan toko** atau **Admin → Toko → ikon kartu**, lalu isi merchant ID dan status merchant setiap toko.

6. Untuk disbursement: isi bank, nomor rekening, dan nama pemilik pada pengaturan pembayaran toko, lalu klik **Verifikasi rekening**. Status terverifikasi hanya diberikan setelah respons API Midtrans dan nama pemilik cocok.

7. Opsional, jalankan scheduler untuk menutup transaksi pending yang tidak pernah memperoleh tautan Snap:

   ```bash
   php artisan schedule:work
   ```

   Perintahnya adalah `php artisan payments:expire`. Transaksi yang sudah memiliki tautan Snap akan diperiksa melalui Get Status API terlebih dahulu, bukan langsung dianggap gagal.

### Notifikasi, idempotensi, dan alokasi

- Signature diverifikasi dengan `sha512(order_id + status_code + gross_amount + server_key)`.
- Nominal, mata uang, dan relasi `order_id` ke percobaan pembayaran terbaru divalidasi sebelum status diubah.
- Hanya `settlement` atau `capture` dengan fraud status `accept` yang menandai seluruh pesanan terkait lunas. Proses ini atomik dan idempoten.
- `pending`/`authorize` mempertahankan status pending. `capture` dengan fraud `challenge` tetap pending sampai ada keputusan.
- `expire`, `cancel`, `deny`, dan `failure` menutup transaksi. Stok dipulihkan tepat satu kali per siklus transaksi. Status terminal bersifat final; callback `pending` atau status terminal lain yang datang terlambat tidak dapat menghidupkan kembali transaksi.
- Percobaan pembayaran baru setelah transaksi terminal akan memesan ulang stok dan memakai ID Midtrans baru. Callback dari percobaan lama ditolak karena bukan percobaan terbaru.
- Alokasi split menjadi `completed` setelah settlement. Alokasi disbursement bergerak `ready → approved → processing → completed/failed`; status `completed` hanya berasal dari respons Midtrans dan tidak dapat diubah oleh sinkronisasi berikutnya.
- Setiap pencairan disbursement mengirim header `X-Idempotency-Key` unik per alokasi (maksimal 46 karakter). Jika request gagal sebelum status akhir diterima, percobaan ulang memakai key yang sama agar pencairan yang ambigu tidak terkirim dua kali.
- Pencairan hanya dikirim jika rekening toko masih terverifikasi dan snapshot bank/nomor/nama pemilik pada alokasi sama persis dengan rekening toko saat ini. Jika rekening diubah, transaksi lama harus dibuat ulang agar dana tidak dikirim ke rekening lama.
- Transfer/QRIS simulasi dan COD tidak pernah membuat transaksi Midtrans dan tidak memakai alokasi online.

### Batasan yang harus dikonfirmasi ke Midtrans

- Ketersediaan Partner transaction split untuk akun Sikitaya.
- Ketersediaan disbursement API/Iris dan proses onboarding merchant/bank.
- URL publik untuk callback Sandbox. Tanpa tunnel/domain publik, pembayaran dapat dibuat tetapi callback tidak akan sampai ke aplikasi lokal.
- Tarif MDR final. Aplikasi mencatat biaya Midtrans ditanggung platform; nominal tagihan aktual tetap mengikuti dashboard Midtrans.

## Aturan Unggah File

| Jenis file | Format | Ukuran maksimum | Lokasi penyimpanan |
| --- | --- | --- | --- |
| Foto produk | JPG, JPEG, PNG, WEBP | 2 MB | `storage/app/public/products` |
| Logo toko | JPG, JPEG, PNG, WEBP | 2 MB | `storage/app/public/store-logos` |
| Bukti pembayaran | JPG, JPEG, PNG, WEBP, PDF | 2 MB | `storage/app/public/payment-proofs` |

Pastikan `php artisan storage:link` sudah dijalankan agar file dapat diakses publik.

## Laporan Penjualan

- Penjual: laporan hanya mencakup pesanan milik tokonya.
- Admin: laporan mencakup seluruh toko dan dapat difilter berdasarkan toko tertentu.
- Filter tersedia: rentang tanggal (`from`, `to`), status pesanan, dan untuk admin juga `store_id`.
- Ringkasan laporan: jumlah pesanan, jumlah item, omzet (status `dibayar`, `dikirim`, `selesai`), dan jumlah pesanan menunggu.
- Ekspor tersedia dalam `.xlsx` dan `.pdf`.

## Pengujian

Pengujian menggunakan SQLite in-memory yang dikonfigurasi di `phpunit.xml`, sehingga tidak menyentuh database MySQL pengembangan.

```bash
php artisan test
```

Cakupan pengujian:

- Autentikasi, registrasi pembeli/penjual, akun nonaktif, dan pengalihan berdasarkan peran
- Keranjang, validasi stok, larangan membeli dari toko sendiri
- Checkout multi-toko dengan transfer/QRIS simulasi dan COD, termasuk pemecahan pesanan per toko, pengurangan stok, dan pengosongan keranjang
- Checkout Midtrans gabungan, token checkout, alokasi split/disbursement, isolasi COD, serta penolakan Midtrans saat checkout dinonaktifkan
- Validasi signature notifikasi, nominal, mata uang, ID pesanan, idempotensi callback, dan status terminal
- Percobaan pembayaran ulang, pemulihan stok satu kali, serta pemesanan ulang stok setelah transaksi terminal
- Otorisasi admin alokasi/pencairan dan visibilitas read-only penjual
- Unggah bukti bayar transfer/QRIS, validasi file, dan otorisasi pemilik pesanan
- Transisi status transfer/QRIS simulasi dan COD, verifikasi penjual, pengiriman, dan konfirmasi pembeli
- Otorisasi halaman penjual/admin dan perlindungan data antar-toko/antar-pembeli
- Laporan penjualan, filter, serta unduhan Excel/PDF
- Perhitungan ongkir dummy dan endpoint ongkir
- Halaman publik, katalog, pencarian, dan penyembunyian produk/toko nonaktif

## Asumsi dan Keputusan PRD

Beberapa hal yang tidak dirinci eksplisit di PRD diputuskan sebagai berikut:

1. **Akun wajib.** Pembeli harus memiliki akun untuk menggunakan keranjang, checkout, dan melihat riwayat pesanan.
2. **Satu checkout, banyak pesanan.** Produk dari beberapa toko dalam satu keranjang diproses dalam satu checkout, tetapi menghasilkan satu pesanan per toko agar ongkir, status, dan verifikasi pembayaran terpisah.
3. **Pembayaran manual aktif, Midtrans disimpan.** Checkout baru menawarkan transfer bank/QRIS simulasi dengan bukti bayar dan COD. Kode Midtrans dipertahankan tetapi baru ditawarkan jika `MIDTRANS_CHECKOUT_ENABLED=true` dan prasyarat split/disbursement siap.
4. **Verifikasi pembayaran manual oleh penjual, pembayaran Midtrans oleh gateway.** Untuk transfer/QRIS simulasi, penjual memverifikasi bukti bayar. Untuk Midtrans, penjual tidak dapat mengonfirmasi pembayaran secara manual; status hanya berubah dari callback/Get Status yang tervalidasi.
5. **COD tidak memerlukan bukti bayar.** Penjual mengonfirmasi pesanan COD menjadi `dibayar`; `paid_at` baru diisi saat pembeli menyelesaikan pesanan.
6. **Ongkir per toko.** Setiap toko memilih layanan kurir sendiri pada checkout; biaya tidak digabung antar toko.
7. **Stok dikurangi saat checkout dan dipulihkan sekali saat terminal.** Transaksi Midtrans yang gagal/kedaluwarsa/dibatalkan mengembalikan stok satu kali. Pembayaran ulang akan memesan ulang stok dan membuat ID Midtrans baru.
8. **Laporan berbasis tanggal pembuatan pesanan.** Omzet hanya menghitung pesanan berstatus `dibayar`, `dikirim`, atau `selesai`.
9. **Admin mengelola alokasi, bukan memverifikasi pembayaran manual.** Admin dapat menyetujui alokasi disbursement dan memicu API Midtrans, tetapi tidak dapat menandai dana sudah dikirim tanpa respons gateway.
10. **Kategori dan toko dikelola admin.** Penjual tidak dapat membuat kategori baru; kategori disediakan admin.
11. **Tanpa notifikasi email/WhatsApp otomatis.** Perubahan status terlihat di dashboard dan halaman pesanan masing-masing pengguna.
12. **Aset demo lokal.** Foto produk, logo toko, dan QRIS demo memakai aset lokal; beberapa aset lama yang tidak terpakai telah dihapus.

## Struktur Singkat

```text
app/
  Enums/                 Status pesanan, metode pembayaran, status pembayaran/alokasi
  Exports/               Kelas ekspor Excel
  Http/Controllers/      Controller publik, auth, pembeli, penjual, admin, notifikasi Midtrans
  Http/Middleware/       Pemeriksaan peran, akun aktif, kelengkapan toko
  Models/                User, Store, Product, Category, CartItem, Order, PaymentTransaction, PaymentAllocation
  Services/              ShippingService, OrderService, MidtransGatewayService, MidtransPaymentService, PaymentAllocationService
database/
  data/                  Dataset kota RajaOngkir untuk mode dummy
  factories/             Factory pengujian
  migrations/            Skema database, termasuk transaksi/alokasi Midtrans
  seeders/               Data awal dan data demo
resources/views/         Blade untuk publik, auth, pembeli, penjual, admin, pembayaran, ekspor
routes/web.php           Seluruh rute web dan endpoint notifikasi Midtrans
tests/Feature/           Pengujian fitur, termasuk fake gateway Midtrans
public/css, public/js    Aset frontend kustom
```

## Troubleshooting

- **Gambar/logo/bukti bayar tidak tampil:** jalankan `php artisan storage:link`.
- **Gagal koneksi MySQL:** pastikan database `sikitaya` sudah dibuat dan kredensial `.env` benar.
- **Ongkir gagal di mode API:** isi `RAJAONGKIR_API_KEY`, atau kembalikan `RAJAONGKIR_MODE=dummy`.
- **Midtrans tidak muncul di checkout:** kondisi bawaan memang manual. Set `MIDTRANS_CHECKOUT_ENABLED=true`, lalu periksa key Midtrans, status integrasi di **Admin → Pembayaran**, merchant ID/bank toko, dan aktifnya split atau disbursement.
- **Checkout menampilkan pesan Midtrans belum siap:** flag checkout aktif tetapi prasyarat split/disbursement atau kesiapan toko belum lengkap. Transfer/QRIS simulasi dan COD tetap dapat digunakan.
- **Notifikasi Midtrans tidak masuk:** pastikan URL callback memakai domain/tunnel publik, bukan `localhost`, dan route `/midtrans/notification` dapat diakses tanpa login.
- **Callback ditolak 422:** nominal, mata uang, atau `order_id` tidak cocok dengan percobaan pembayaran terbaru. Periksa log webhook di detail pembayaran admin.
- **Ekspor Excel gagal:** pastikan ekstensi PHP `zip` aktif.
- **Ekspor PDF gagal:** pastikan folder `storage/framework/views` dan `storage/fonts` dapat ditulis.
- **Data demo ingin diulang:** jalankan `php artisan migrate:fresh --seed` (menghapus seluruh data).

## Keamanan

Password akun demo hanya untuk pengembangan. Ganti password, `APP_KEY`, dan kredensial database sebelum men-deploy aplikasi ke lingkungan produksi. Jangan mengaktifkan `APP_DEBUG=true` di produksi.
