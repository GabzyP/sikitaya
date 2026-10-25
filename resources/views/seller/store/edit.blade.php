@extends('layouts.dashboard')

@section('title', 'Profil Toko')
@section('eyebrow', 'Pengaturan')
@section('page_title', 'Profil toko')

@section('content')
    <div class="page-head">
        <div>
            <h1>Profil toko</h1>
            <p>Informasi ini tampil di halaman toko dan digunakan sebagai kota asal pengiriman.</p>
        </div>
        @if ($store)
            <div class="actions">
                <a href="{{ route('stores.show', $store) }}" target="_blank" class="btn btn-outline-brand btn-sm btn-dash"><i class="bi bi-box-arrow-up-right me-1"></i> Lihat toko publik</a>
            </div>
        @endif
    </div>

    @if ($shippingError)
        <div class="alert alert-warning app-alert mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>{{ $shippingError }}</div>
        </div>
    @endif

    @if (! $store)
        <div class="empty-state">
            <span class="empty-icon"><i class="bi bi-shop"></i></span>
            <h2>Profil toko tidak ditemukan</h2>
            <p>Akun Anda belum terhubung dengan toko. Hubungi admin Sikitaya untuk bantuan.</p>
        </div>
    @else
        <form action="{{ route('seller.store.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="dash-form-panel">
                        <h2>Informasi toko</h2>

                        <div class="mb-3">
                            <label for="name" class="form-label">Nama toko</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $store->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Nama toko Anda" required maxlength="100">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Tentang toko</label>
                            <textarea id="description" name="description" rows="5" class="form-control @error('description') is-invalid @enderror" placeholder="Ceritakan produk, cerita usaha, dan keunikan toko Anda" maxlength="1000">{{ old('description', $store->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Maksimal 1.000 karakter.</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Nomor WhatsApp / telepon</label>
                                <input type="tel" id="phone" name="phone" value="{{ old('phone', $store->phone) }}" class="form-control @error('phone') is-invalid @enderror" placeholder="08xxxxxxxxxx" maxlength="20">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="city_id" class="form-label">Kota / kabupaten asal pengiriman</label>
                                <select id="city_id" name="city_id" class="form-select @error('city_id') is-invalid @enderror" required>
                                    <option value="">Pilih kota asal</option>
                                    @foreach ($cities->groupBy('province') as $province => $provinceCities)
                                        <optgroup label="{{ $province }}">
                                            @foreach ($provinceCities as $city)
                                                <option value="{{ $city['id'] }}" @selected((string) old('city_id', $store->city_id) === (string) $city['id'])>
                                                    {{ $city['city_name'] }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('city_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Ongkir dihitung dari kota ini. Saat ini: <strong>{{ $store->city_name }}</strong>.</div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="address" class="form-label">Alamat lengkap toko</label>
                            <textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror" placeholder="Nama jalan, nomor, kelurahan, kecamatan" maxlength="500">{{ old('address', $store->address) }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="dash-form-panel mb-3">
                        <h2>Logo toko</h2>
                        <div class="image-upload text-center">
                            @if ($store->logo_url)
                                <img src="{{ $store->logo_url }}" alt="Logo {{ $store->name }}" class="image-upload-preview mb-2" id="logo-preview" style="width:110px;height:110px;border-radius:18px;">
                            @else
                                <div class="avatar-store mx-auto mb-2" id="logo-preview" style="width:110px;height:110px;border-radius:18px;font-size:2rem;">{{ $store->initials }}</div>
                            @endif
                            <label for="logo" class="form-label d-block">Ganti logo</label>
                            <input type="file" id="logo" name="logo" class="form-control form-control-sm @error('logo') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp" data-image-input="#logo-preview">
                            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text mt-2">JPG, PNG, atau WEBP · maksimal 2 MB.</div>
                        </div>
                    </div>

                    <div class="dash-form-panel mb-3">
                        <h2>Status toko</h2>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted" style="font-size:.74rem;">Terdaftar sejak</span>
                            <strong style="font-size:.74rem;">{{ $store->created_at->locale('id')->translatedFormat('d M Y') }}</strong>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <span class="text-muted" style="font-size:.74rem;">Status</span>
                            <span class="badge-soft {{ $store->is_active ? 'green' : 'red' }}">{{ $store->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                        <p class="mt-2 mb-0" style="font-size:.68rem;color:var(--muted);">Perubahan status toko dilakukan oleh admin platform.</p>
                    </div>

                    <div class="dash-form-panel">
                        <h2>Simpan profil</h2>
                        <p style="font-size:.74rem;color:var(--muted);">Pastikan kota asal sudah benar agar ongkir yang tampil kepada pembeli akurat.</p>
                        <button type="submit" class="btn btn-brand w-100"><i class="bi bi-check2 me-1"></i> Simpan profil</button>
                    </div>
                </div>
            </div>
        </form>
    @endif
@endsection
