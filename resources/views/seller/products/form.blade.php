@extends('layouts.dashboard')

@section('title', $product->exists ? 'Ubah Produk' : 'Tambah Produk')
@section('eyebrow', 'Katalog Toko')
@section('page_title', $product->exists ? 'Ubah produk' : 'Tambah produk')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $product->exists ? 'Ubah produk' : 'Tambah produk baru' }}</h1>
            <p>{{ $product->exists ? 'Perbarui informasi produk yang tampil di toko Anda.' : 'Lengkapi detail produk agar mudah ditemukan pembeli.' }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('seller.products.index') }}" class="btn btn-ghost btn-sm btn-dash"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        </div>
    </div>

    <form action="{{ $product->exists ? route('seller.products.update', $product) : route('seller.products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if ($product->exists)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="dash-form-panel">
                    <h2>Informasi produk</h2>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama produk</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Kopi Arabika Gayo 250g" required maxlength="120">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="category_id" class="form-label">Kategori</label>
                            <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror">
                                <option value="">Tanpa kategori</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="weight" class="form-label">Berat per item (gram)</label>
                            <input type="number" id="weight" name="weight" value="{{ old('weight', $product->weight ?: 500) }}" class="form-control @error('weight') is-invalid @enderror" min="1" max="50000" required>
                            @error('weight')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Digunakan untuk menghitung ongkir.</div>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label for="description" class="form-label">Deskripsi produk</label>
                        <textarea id="description" name="description" rows="6" class="form-control @error('description') is-invalid @enderror" placeholder="Ceritakan bahan, ukuran, keunggulan, dan cara penyajian produk" maxlength="2000">{{ old('description', $product->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Maksimal 2.000 karakter.</div>
                    </div>

                    <div class="form-section-title">Harga & stok</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="price" class="form-label">Harga jual</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" id="price" name="price" value="{{ old('price', $product->price) }}" class="form-control @error('price') is-invalid @enderror" min="100" max="1000000000" step="100" placeholder="45000" required>
                            </div>
                            @error('price')<div class="text-danger mt-1" style="font-size:.76rem;">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="stock" class="form-label">Stok tersedia</label>
                            <input type="number" id="stock" name="stock" value="{{ old('stock', $product->stock) }}" class="form-control @error('stock') is-invalid @enderror" min="0" max="100000" required>
                            @error('stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
                        <label class="form-check-label" for="is_active" style="font-size:.8rem;font-weight:600;">Tampilkan produk di katalog</label>
                        <div class="form-text">Produk nonaktif tidak dapat dilihat atau dibeli pembeli.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="dash-form-panel mb-3">
                    <h2>Foto produk</h2>
                    <div class="image-upload">
                        @if ($product->image)
                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="image-upload-preview mb-2" id="image-preview">
                        @else
                            <img src="{{ asset('images/products/placeholder.svg') }}" alt="Pratinjau produk" class="image-upload-preview mb-2" id="image-preview">
                        @endif
                        <label for="image" class="form-label">Unggah foto</label>
                        <input type="file" id="image" name="image" class="form-control form-control-sm @error('image') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp" data-image-input="#image-preview">
                        @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text mt-2">JPG, PNG, atau WEBP · maksimal 2 MB. Rasio 4:3 disarankan.</div>
                    </div>
                </div>

                <div class="dash-form-panel">
                    <h2>Simpan perubahan</h2>
                    <p style="font-size:.74rem;color:var(--muted);">Pastikan harga dan stok sudah benar sebelum menyimpan.</p>
                    <button type="submit" class="btn btn-brand w-100"><i class="bi bi-check2 me-1"></i> {{ $product->exists ? 'Simpan perubahan' : 'Tambahkan produk' }}</button>
                    <a href="{{ route('seller.products.index') }}" class="btn btn-ghost w-100 mt-2">Batal</a>
                </div>
            </div>
        </div>
    </form>
@endsection
