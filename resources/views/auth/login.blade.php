@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <h1 class="auth-title">Selamat datang kembali</h1>
    <p class="auth-sub">Masuk untuk melanjutkan belanja atau mengelola toko Anda.</p>

    <form method="POST" action="{{ route('login.store') }}" class="auth-form" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Alamat email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="nama@email.com" required autofocus autocomplete="email">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-2">
            <label for="password" class="form-label">Kata sandi</label>
            <div class="password-field">
                <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Masukkan kata sandi" required autocomplete="current-password">
                <button type="button" class="password-toggle" data-toggle-password="#password" aria-label="Tampilkan kata sandi" aria-pressed="false">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                <label class="form-check-label small" for="remember">Ingat saya</label>
            </div>
            <span class="small text-muted">Lupa kata sandi? Hubungi admin.</span>
        </div>

        <button type="submit" class="btn btn-brand">Masuk ke akun <i class="bi bi-arrow-right ms-1"></i></button>
    </form>

    <div class="auth-divider">atau</div>
    <p class="auth-alt">Belum punya akun? <a href="{{ route('register') }}">Daftar sekarang</a></p>

    @if (app()->environment('local'))
        <div class="demo-accounts">
            <p>Akun demo untuk mencoba</p>
            <div class="demo-account-row">
                <span><i class="bi bi-shield-check text-brand me-1"></i> Admin</span>
                <code>admin@sikitaya.test · password</code>
            </div>
            <div class="demo-account-row">
                <span><i class="bi bi-shop text-brand me-1"></i> Penjual</span>
                <code>sari@sikitaya.test · password</code>
            </div>
            <div class="demo-account-row">
                <span><i class="bi bi-bag text-brand me-1"></i> Pembeli</span>
                <code>pembeli@sikitaya.test · password</code>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            document.querySelectorAll('[data-toggle-password]').forEach((button) => {
                button.addEventListener('click', () => {
                    const input = document.querySelector(button.getAttribute('data-toggle-password'));
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    button.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
                    button.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
                    button.setAttribute('aria-pressed', show ? 'true' : 'false');
                });
            });
        </script>
    @endpush
@endsection
