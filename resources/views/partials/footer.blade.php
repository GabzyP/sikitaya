<footer class="site-footer">
    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <a href="{{ route('home') }}" class="brand brand-light text-decoration-none">
                    <span class="brand-mark">S</span>
                    <span class="brand-text">
                        <span class="brand-name">Sikitaya</span>
                        <span class="brand-tag">UMKM KOTA MEDAN</span>
                    </span>
                </a>
                <p>Marketplace yang menghubungkan pembeli dengan pelaku UMKM Kota Medan. Belanja produk lokal, dukung usaha warga.</p>
                <div class="footer-social">
                    <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                    <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="#" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h3>Jelajahi</h3>
                <a href="{{ route('products.index') }}">Semua produk</a>
                <a href="{{ route('stores.index') }}">Toko UMKM</a>
                <a href="{{ route('register') }}">Buka toko</a>
                <a href="{{ route('orders.index') }}">Lacak pesanan</a>
            </div>
            <div class="footer-col">
                <h3>Bantuan</h3>
                <a href="{{ route('login') }}">Masuk akun</a>
                <a href="{{ route('register') }}">Cara berbelanja</a>
                <a href="#">Pengiriman</a>
                <a href="#">Pembayaran</a>
            </div>
            <div class="footer-col footer-contact">
                <h3>Kontak</h3>
                <p><i class="bi bi-geo-alt"></i> Kota Medan, Sumatera Utara</p>
                <p><i class="bi bi-envelope"></i> halo@sikitaya.test</p>
                <p><i class="bi bi-telephone"></i> 0813-6000-2026</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© {{ date('Y') }} Sikitaya. Dibangun untuk UMKM Kota Medan.</p>
            <div>
                <span>Laravel</span>
                <span>Bootstrap 5</span>
                <span>RajaOngkir Dummy</span>
            </div>
        </div>
    </div>
</footer>
