@extends('layouts.dashboard')

@section('title', $category->exists ? 'Ubah Kategori' : 'Tambah Kategori')
@section('eyebrow', 'Katalog')
@section('page_title', $category->exists ? 'Ubah kategori' : 'Tambah kategori')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $category->exists ? 'Ubah kategori' : 'Tambah kategori baru' }}</h1>
            <p>Kategori membantu pembeli menjelajahi produk sesuai kebutuhan.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost btn-sm btn-dash"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <form action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" method="POST">
                @csrf
                @if ($category->exists)
                    @method('PUT')
                @endif

                <div class="dash-form-panel">
                    <h2>Informasi kategori</h2>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama kategori</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $category->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Kuliner" required maxlength="60">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Nama kategori harus unik.</div>
                    </div>

                    <div class="mb-3">
                        <label for="icon" class="form-label">Ikon Bootstrap Icons</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi {{ old('icon', $category->icon ?: 'bi-grid') }}" id="icon-preview"></i></span>
                            <input type="text" id="icon" name="icon" value="{{ old('icon', $category->icon ?: 'bi-grid') }}" class="form-control @error('icon') is-invalid @enderror" placeholder="bi-bag-heart" required maxlength="60" pattern="bi-[a-z0-9-]+">
                        </div>
                        @error('icon')<div class="text-danger mt-1" style="font-size:.76rem;">{{ $message }}</div>@enderror
                        <div class="form-text">Gunakan format <code>bi-nama-ikon</code>, misalnya <code>bi-bag-heart</code>, <code>bi-cup-hot</code>, atau <code>bi-palette</code>.</div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi <span class="text-muted fw-normal">(opsional)</span></label>
                        <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror" placeholder="Deskripsi singkat kategori ini" maxlength="500">{{ old('description', $category->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Maksimal 500 karakter.</div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-brand"><i class="bi bi-check2 me-1"></i> {{ $category->exists ? 'Simpan perubahan' : 'Tambah kategori' }}</button>
                        <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost">Batal</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="dash-form-panel">
                <h2>Pratinjau</h2>
                <div class="d-flex align-items-center gap-2">
                    <span class="category-icon" style="width:52px;height:52px;">
                        <i class="bi {{ old('icon', $category->icon ?: 'bi-grid') }}" id="icon-preview-large"></i>
                    </span>
                    <div>
                        <strong style="font-size:.84rem;">{{ old('name', $category->name) ?: 'Nama kategori' }}</strong>
                        <span class="d-block text-muted" style="font-size:.68rem;">{{ $category->products_count ?? 0 }} produk</span>
                    </div>
                </div>
                <p class="mt-3 mb-0" style="font-size:.7rem;color:var(--muted);">Ikon kategori tampil di beranda dan filter katalog.</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const iconInput = document.getElementById('icon');
        if (iconInput) {
            iconInput.addEventListener('input', () => {
                const icon = iconInput.value.trim() || 'bi-grid';
                document.getElementById('icon-preview')?.setAttribute('class', 'bi ' + icon);
                document.getElementById('icon-preview-large')?.setAttribute('class', 'bi ' + icon);
            });
        }
    </script>
@endpush
