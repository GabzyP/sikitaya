@extends('layouts.dashboard')

@section('title', 'Pembayaran '.$store->name)
@section('eyebrow', 'Keuangan')
@section('page_title', 'Pengaturan pembayaran toko')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $store->name }}</h1>
            <p>Atur merchant ID Partner Midtrans dan rekening penerima dana penjual.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.payments.index') }}" class="btn btn-ghost btn-sm btn-dash"><i class="bi bi-arrow-left me-1"></i> Pembayaran</a>
            <a href="{{ route('stores.show', $store) }}" target="_blank" class="btn btn-outline-brand btn-sm btn-dash"><i class="bi bi-box-arrow-up-right me-1"></i> Lihat toko</a>
        </div>
    </div>

    <div class="detail-panel">
        <h2><i class="bi bi-shop"></i> Informasi toko</h2>
        <div class="detail-list">
            <div><span class="label">Pemilik</span><span class="value">{{ $store->owner?->name }} · {{ $store->owner?->email }}</span></div>
            <div><span class="label">Kota</span><span class="value">{{ $store->city_name }}</span></div>
            <div><span class="label">Status toko</span><span class="value">{{ $store->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
        </div>
    </div>

    <form action="{{ route('admin.store-payments.update', $store) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="detail-grid">
            <div>
                <div class="detail-panel">
                    <h2><i class="bi bi-diagram-3"></i> Partner transaction split</h2>
                    <p class="cell-sub">Isi merchant ID yang terdaftar di bawah Partner Sikitaya. Split otomatis dipakai lebih dulu jika semua toko pada checkout siap.</p>

                    <div class="mb-3">
                        <label for="midtrans_merchant_id" class="form-label">Merchant ID Midtrans</label>
                        <input type="text" id="midtrans_merchant_id" name="midtrans_merchant_id" value="{{ old('midtrans_merchant_id', $store->midtrans_merchant_id) }}" class="form-control @error('midtrans_merchant_id') is-invalid @enderror" placeholder="Contoh: G123456789" maxlength="100">
                        @error('midtrans_merchant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-0">
                        <label for="midtrans_merchant_status" class="form-label">Status merchant</label>
                        <select id="midtrans_merchant_status" name="midtrans_merchant_status" class="form-select @error('midtrans_merchant_status') is-invalid @enderror" required>
                            @foreach (\App\Enums\MidtransMerchantStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(old('midtrans_merchant_status', $store->midtrans_merchant_status?->value ?? 'unregistered') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('midtrans_merchant_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="detail-panel">
                    <h2><i class="bi bi-bank"></i> Rekening penerima disbursement</h2>
                    <p class="cell-sub">Rekening hanya dipakai jika jalur disbursement API aktif. Nomor rekening disimpan terenkripsi.</p>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="payout_bank_code" class="form-label">Kode bank</label>
                            <input type="text" id="payout_bank_code" name="payout_bank_code" value="{{ old('payout_bank_code', $store->payout_bank_code) }}" class="form-control @error('payout_bank_code') is-invalid @enderror" placeholder="Contoh: bca" maxlength="20">
                            @error('payout_bank_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label for="payout_bank_name" class="form-label">Nama bank</label>
                            <input type="text" id="payout_bank_name" name="payout_bank_name" value="{{ old('payout_bank_name', $store->payout_bank_name) }}" class="form-control @error('payout_bank_name') is-invalid @enderror" placeholder="Contoh: Bank Central Asia" maxlength="100">
                            @error('payout_bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label for="payout_account_number" class="form-label">Nomor rekening</label>
                            <input type="text" id="payout_account_number" name="payout_account_number" value="{{ old('payout_account_number', $store->payout_account_number) }}" class="form-control @error('payout_account_number') is-invalid @enderror" placeholder="Hanya angka" inputmode="numeric" maxlength="50">
                            @error('payout_account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label for="payout_account_name" class="form-label">Nama pemilik rekening</label>
                            <input type="text" id="payout_account_name" name="payout_account_name" value="{{ old('payout_account_name', $store->payout_account_name) }}" class="form-control @error('payout_account_name') is-invalid @enderror" placeholder="Sesuai buku tabungan" maxlength="100">
                            @error('payout_account_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    @if ($store->payout_account_verified_at)
                        <div class="alert alert-success py-2 px-3 mt-3 mb-0" style="font-size:.74rem;">
                            <i class="bi bi-check-circle-fill me-1"></i>
                            Rekening terverifikasi pada {{ tanggal_indonesia($store->payout_account_verified_at, true) }}.
                            Mengubah data rekening akan mereset status verifikasi.
                        </div>
                    @else
                        <div class="alert alert-warning py-2 px-3 mt-3 mb-0" style="font-size:.74rem;">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Rekening belum diverifikasi. Gunakan tombol validasi Midtrans setelah menyimpan data.
                        </div>
                    @endif
                </div>
            </div>

            <aside>
                <div class="detail-panel">
                    <h2><i class="bi bi-check2-square"></i> Simpan & verifikasi</h2>
                    <p class="cell-sub">Validasi rekening memanggil API disbursement Midtrans dan mencocokkan nama pemilik.</p>
                    <button type="submit" class="btn btn-brand w-100"><i class="bi bi-save me-1"></i> Simpan pengaturan</button>
                </div>
            </aside>
        </div>
    </form>

    <form action="{{ route('admin.store-payments.verify', $store) }}" method="POST" class="mb-3">
        @csrf
        <div class="detail-panel mb-0">
            <h2><i class="bi bi-shield-check"></i> Validasi rekening ke Midtrans</h2>
            <p class="cell-sub">Pastikan data rekening sudah disimpan. Validasi hanya dapat dilakukan jika MIDTRANS_DISBURSEMENT_ENABLED dan kredensial Iris tersedia.</p>
            <button type="submit" class="btn btn-outline-brand" @disabled(blank($store->payout_bank_code) || blank($store->payout_account_number) || blank($store->payout_account_name))>
                <i class="bi bi-patch-check me-1"></i> Verifikasi rekening
            </button>
        </div>
    </form>
@endsection
