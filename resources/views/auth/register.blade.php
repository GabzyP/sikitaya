@extends('layouts.auth')

@section('title', 'Daftar')

@section('content')
    <h1 class="auth-title">Mulai di Sikitaya</h1>
    <p class="auth-sub">Daftar sebagai pembeli, atau buka akun penjual yang otomatis terhubung dengan satu toko UMKM.</p>

    <form method="POST" action="{{ route('register.store') }}" class="auth-form" novalidate>
        @csrf

        <div class="role-selector" role="radiogroup" aria-label="Jenis akun">
            <div class="role-option">
                <input type="radio" name="role" id="role-buyer" value="buyer" @checked(old('role', 'buyer') === 'buyer')>
                <label for="role-buyer">
                    <i class="bi bi-bag-heart"></i>
                    <span>Pembeli <small>Belanja produk lokal</small></span>
                </label>
            </div>
            <div class="role-option">
                <input type="radio" name="role" id="role-seller" value="seller" @checked(old('role') === 'seller')>
                <label for="role-seller">
                    <i class="bi bi-shop"></i>
                    <span>Penjual &amp; Toko <small>Satu akun untuk satu toko UMKM</small></span>
                </label>
            </div>
        </div>

        <div class="mb-3">
            <label for="name" class="form-label">Nama lengkap</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Nama sesuai identitas" required autofocus autocomplete="name">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Alamat email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="nama@email.com" required autocomplete="email">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Nomor WhatsApp <span class="text-muted fw-normal">(opsional)</span></label>
            <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror" placeholder="08xxxxxxxxxx" autocomplete="tel">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div data-store-fields hidden>
            <div class="alert alert-success app-alert py-2 mb-3" role="alert" style="font-size:.74rem;">
                <i class="bi bi-shop"></i>
                <div>Akun penjual dan toko adalah satu kesatuan. Satu akun hanya mengelola satu toko.</div>
            </div>
            <div class="mb-3">
                <label for="store_name" class="form-label">Nama toko</label>
                <input id="store_name" type="text" name="store_name" value="{{ old('store_name') }}" class="form-control @error('store_name') is-invalid @enderror" placeholder="Contoh: Dapur Bu Sari" data-required-when-seller="true">
                @error('store_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="store_description" class="form-label">Tentang toko <span class="text-muted fw-normal">(opsional)</span></label>
                <textarea id="store_description" name="store_description" rows="2" class="form-control @error('store_description') is-invalid @enderror" placeholder="Ceritakan singkat produk yang Anda jual">{{ old('store_description') }}</textarea>
                @error('store_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="row g-3 mb-2">
            <div class="col-sm-6">
                <label for="password" class="form-label">Kata sandi</label>
                <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Minimal 8 karakter" required autocomplete="new-password">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6">
                <label for="password_confirmation" class="form-label">Ulangi kata sandi</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" placeholder="Ulangi kata sandi" required autocomplete="new-password">
            </div>
        </div>

        <p class="form-text mb-3">Dengan mendaftar, Anda menyetujui ketentuan penggunaan dan kebijakan privasi Sikitaya.</p>

        <button type="submit" class="btn btn-brand">Buat akun <i class="bi bi-arrow-right ms-1"></i></button>
    </form>

    <div class="auth-divider">atau</div>
    <p class="auth-alt">Sudah punya akun? <a href="{{ route('login') }}">Masuk di sini</a></p>
@endsection
