@extends('layouts.app')

@php
    $isGuest = auth()->guest();
    $searchTerm = request('q');
    $pageTitle = filled($searchTerm) ? 'Hasil toko untuk “'.$searchTerm.'”' : 'Toko UMKM yang bisa Anda dukung';
@endphp

@section('title', $pageTitle)

@section('content')
    <section class="page-banner">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Toko</li>
                </ol>
            </nav>
            @if ($isGuest)
                <span class="guest-note"><i class="bi bi-eye"></i> Mode tamu · toko pilihan</span>
            @endif
            <h1>{{ $pageTitle }}</h1>
            <p>
                @if ($isGuest)
                    Kenali penjual di balik produk UMKM Kota Medan. Masuk untuk melihat semua toko, cerita mereka, dan katalog lengkapnya.
                @else
                    Setiap toko dikelola langsung oleh pelaku UMKM Kota Medan. Kunjungi toko, kenali ceritanya, dan belanja produk unggulan mereka.
                @endif
            </p>
        </div>
    </section>

    <section class="page-section">
        <div class="container">
            <div class="catalog-toolbar">
                <p class="result-count mb-0">
                    @if ($isGuest)
                        Menampilkan <strong>{{ $stores->count() }}</strong> dari <strong>{{ $stores->total() }}</strong> toko UMKM pilihan
                    @else
                        <strong>{{ $stores->total() }}</strong> toko aktif terdaftar di Sikitaya
                    @endif
                </p>
                <form action="{{ route('stores.index') }}" method="GET" class="nav-search" style="min-width:240px;" role="search">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" value="{{ $searchTerm }}" placeholder="Cari nama toko..." aria-label="Cari toko">
                    <button type="submit" class="visually-hidden">Cari</button>
                </form>
            </div>

            @if ($stores->isEmpty())
                <div class="empty-state">
                    <span class="empty-icon"><i class="bi bi-shop"></i></span>
                    <h2>Belum ada toko yang cocok</h2>
                    <p>Coba gunakan kata kunci lain, atau jelajahi seluruh toko UMKM yang ada di Sikitaya.</p>
                    <a href="{{ route('stores.index') }}" class="btn btn-brand">Lihat semua toko</a>
                </div>
            @else
                <div class="store-grid">
                    @foreach ($stores as $store)
                        @include('partials.store-card', ['store' => $store])
                    @endforeach
                </div>

                @if ($isGuest)
                    @include('partials.guest-gate', [
                        'title' => 'Masuk untuk melihat lebih jauh',
                        'message' => 'Masuk atau daftar untuk membuka direktori seluruh toko UMKM, mencari toko favorit, dan melihat katalog mereka.',
                    ])
                @else
                    <div class="mt-4 d-flex justify-content-center">
                        {{ $stores->links() }}
                    </div>
                @endif
            @endif
        </div>
    </section>
@endsection
