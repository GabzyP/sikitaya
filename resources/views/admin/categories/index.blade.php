@extends('layouts.dashboard')

@section('title', 'Kategori')
@section('eyebrow', 'Katalog')
@section('page_title', 'Kategori')

@section('content')
    <div class="page-head">
        <div>
            <h1>Kategori produk</h1>
            <p>Atur pengelompokan produk agar pembeli lebih mudah menemukan yang dicari.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.categories.create') }}" class="btn btn-brand btn-sm btn-dash"><i class="bi bi-plus-lg me-1"></i> Tambah kategori</a>
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-clean">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Slug</th>
                        <th>Deskripsi</th>
                        <th class="text-center">Produk</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="icon-preview"><i class="bi {{ $category->icon }}"></i></span>
                                    <div>
                                        <div class="cell-main">{{ $category->name }}</div>
                                        <div class="cell-sub">{{ $category->icon }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><code style="font-size:.7rem;">{{ $category->slug }}</code></td>
                            <td style="max-width:320px;">{{ Str::limit($category->description, 80) ?: '-' }}</td>
                            <td class="text-center"><span class="badge-soft">{{ $category->products_count }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('products.index', ['category' => $category->slug]) }}" target="_blank" class="btn-table" title="Lihat produk kategori" aria-label="Lihat produk {{ $category->name }}"><i class="bi bi-box-arrow-up-right"></i></a>
                                    <a href="{{ route('admin.categories.edit', $category) }}" class="btn-table" title="Ubah kategori" aria-label="Ubah {{ $category->name }}"><i class="bi bi-pencil"></i></a>
                                    @if ($category->products_count > 0)
                                        <button type="button" class="btn-table" disabled title="Kategori masih digunakan produk" aria-label="Kategori {{ $category->name }} tidak dapat dihapus"><i class="bi bi-lock"></i></button>
                                    @else
                                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" onsubmit="return confirm('Hapus kategori {{ addslashes($category->name) }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-table danger" title="Hapus kategori" aria-label="Hapus {{ $category->name }}"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="empty-state border-0 p-0">
                                    <span class="empty-icon"><i class="bi bi-tags"></i></span>
                                    <h2>Belum ada kategori</h2>
                                    <p>Tambahkan kategori pertama untuk merapikan katalog produk.</p>
                                    <a href="{{ route('admin.categories.create') }}" class="btn btn-brand btn-sm">Tambah kategori</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
