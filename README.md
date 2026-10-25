# Sikitaya — Marketplace UMKM Kota Medan

Sikitaya adalah aplikasi web marketplace multi-vendor untuk UMKM Kota Medan. Pembeli dapat menjelajahi katalog, memasukkan produk dari beberapa toko ke keranjang, memilih ongkir per toko, lalu menyelesaikan pembayaran simulasi. Penjual mengelola toko, produk, pesanan, dan laporan penjualan; admin mengelola pengguna, toko, kategori, pesanan, dan laporan platform.

## Teknologi

- Laravel 12 (PHP >= 8.2; diuji dengan PHP 8.4)
- MySQL 8
- Bootstrap 5 + Bootstrap Icons (CDN), CSS/JS kustom di `public/`
- RajaOngkir Starter, dengan mode dummy bawaan agar aplikasi tetap bisa dijalankan tanpa API key
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
- Keranjang belanja per produk
- Checkout satu kali untuk produk dari beberapa toko
- Pemilihan layanan ongkir terpisah untuk setiap toko
- Simulasi pembayaran: transfer bank, QRIS, atau COD
- Unggah bukti pembayaran untuk transfer/QRIS
- Riwayat pesanan, detail pesanan, dan konfirmasi pesanan selesai

### Penjual

- Profil toko (nama, deskripsi, logo, alamat, kota asal ongkir)
- CRUD produk, stok, harga, berat, gambar, dan status aktif
- Daftar pesanan masuk dan detail pesanan
- Verifikasi bukti pembayaran transfer/QRIS
- Konfirmasi pembayaran COD dan input nomor resi
- Laporan penjualan dengan filter tanggal dan status, ekspor Excel/PDF

### Admin

- Dashboard ringkasan platform
- Aktivasi/nonaktivasi pengguna dan toko
- CRUD kategori
- Daftar dan detail seluruh pesanan
- Laporan penjualan platform dengan filter tanggal, status, dan toko; ekspor Excel/PDF

## Alur Pesanan

Status pesanan: **menunggu → dibayar → dikirim → selesai**.

| Metode | Alur |
| --- | --- |
| Transfer bank / QRIS | Pembeli membuat pesanan (`menunggu`) → mengunggah bukti bayar → penjual memverifikasi (`dibayar`, `paid_at` diisi) → penjual memasukkan resi (`dikirim`) → pembeli mengonfirmasi diterima (`selesai`) |
| COD | Pembeli membuat pesanan (`menunggu`) → penjual mengonfirmasi pesanan (`dibayar`, `paid_at` masih kosong) → penjual mengirim (`dikirim`) → pembeli mengonfirmasi diterima (`selesai`, `paid_at` diisi) |

Satu checkout dapat berisi produk dari beberapa toko. Karena ongkir dan penjual berbeda, aplikasi membuat **satu pesanan per toko**; setiap pesanan memiliki status, ongkir, dan verifikasi pembayaran sendiri.

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
- Checkout multi-toko, pemecahan pesanan per toko, pengurangan stok, dan pengosongan keranjang
- Unggah bukti bayar, validasi file, dan otorisasi pemilik pesanan
- Transisi status transfer/QRIS dan COD, verifikasi penjual, pengiriman, dan konfirmasi pembeli
- Otorisasi halaman penjual/admin dan perlindungan data antar-toko/antar-pembeli
- Laporan penjualan, filter, serta unduhan Excel/PDF
- Perhitungan ongkir dummy dan endpoint ongkir
- Halaman publik, katalog, pencarian, dan penyembunyian produk/toko nonaktif

## Asumsi dan Keputusan PRD

Beberapa hal yang tidak dirinci eksplisit di PRD diputuskan sebagai berikut:

1. **Akun wajib.** Pembeli harus memiliki akun untuk menggunakan keranjang, checkout, dan melihat riwayat pesanan.
2. **Satu checkout, banyak pesanan.** Produk dari beberapa toko dalam satu keranjang diproses dalam satu checkout, tetapi menghasilkan satu pesanan per toko agar ongkir, status, dan verifikasi pembayaran terpisah.
3. **Pembayaran disimulasikan di dalam aplikasi.** Tidak ada integrasi payment gateway seperti Midtrans/Xendit. Halaman pembayaran menampilkan instruksi transfer/QRIS statis dan tombol unggah bukti.
4. **Verifikasi pembayaran oleh penjual.** Untuk transfer/QRIS, penjual memeriksa bukti bayar lalu menandai pesanan sebagai `dibayar`.
5. **COD tidak memerlukan bukti bayar.** Penjual mengonfirmasi pesanan COD menjadi `dibayar`; `paid_at` baru diisi saat pembeli menyelesaikan pesanan.
6. **Ongkir per toko.** Setiap toko memilih layanan kurir sendiri pada checkout; biaya tidak digabung antar toko.
7. **Stok dikurangi saat checkout.** Stok langsung berkurang ketika pesanan dibuat. Pembatalan otomatis dan pengembalian stok untuk pesanan yang tidak dibayar belum termasuk dalam cakupan versi ini.
8. **Laporan berbasis tanggal pembuatan pesanan.** Omzet hanya menghitung pesanan berstatus `dibayar`, `dikirim`, atau `selesai`.
9. **Admin tidak memverifikasi pembayaran.** Verifikasi pembayaran adalah tanggung jawab penjual masing-masing toko; admin dapat melihat seluruh pesanan untuk pengawasan.
10. **Kategori dan toko dikelola admin.** Penjual tidak dapat membuat kategori baru; kategori disediakan admin.
11. **Tanpa notifikasi email/WhatsApp otomatis.** Perubahan status terlihat di dashboard dan halaman pesanan masing-masing pengguna.
12. **Aset demo lokal.** Seluruh foto produk, logo toko, dan bukti pembayaran demo memakai aset SVG/PNG lokal agar aplikasi dapat dijalankan offline.

## Struktur Singkat

```text
app/
  Enums/                 Status pesanan, metode pembayaran, peran pengguna
  Exports/               Kelas ekspor Excel
  Http/Controllers/      Controller publik, auth, pembeli, penjual, admin
  Http/Middleware/       Pemeriksaan peran, akun aktif, kelengkapan toko
  Models/                User, Store, Product, Category, CartItem, Order, OrderItem
  Services/              ShippingService, OrderService, SalesReportService
database/
  data/                  Dataset kota RajaOngkir untuk mode dummy
  factories/             Factory pengujian
  migrations/            Skema database
  seeders/               Data awal dan data demo
resources/views/         Blade untuk publik, auth, pembeli, penjual, admin, ekspor
routes/web.php           Seluruh rute web
tests/Feature/           Pengujian fitur
public/css, public/js    Aset frontend kustom
```

## Troubleshooting

- **Gambar/logo/bukti bayar tidak tampil:** jalankan `php artisan storage:link`.
- **Gagal koneksi MySQL:** pastikan database `sikitaya` sudah dibuat dan kredensial `.env` benar.
- **Ongkir gagal di mode API:** isi `RAJAONGKIR_API_KEY`, atau kembalikan `RAJAONGKIR_MODE=dummy`.
- **Ekspor Excel gagal:** pastikan ekstensi PHP `zip` aktif.
- **Ekspor PDF gagal:** pastikan folder `storage/framework/views` dan `storage/fonts` dapat ditulis.
- **Data demo ingin diulang:** jalankan `php artisan migrate:fresh --seed` (menghapus seluruh data).

## Keamanan

Password akun demo hanya untuk pengembangan. Ganti password, `APP_KEY`, dan kredensial database sebelum men-deploy aplikasi ke lingkungan produksi. Jangan mengaktifkan `APP_DEBUG=true` di produksi.
