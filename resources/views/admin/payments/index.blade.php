@extends('layouts.dashboard')

@section('title', 'Pembayaran Midtrans')
@section('eyebrow', 'Keuangan')
@section('page_title', 'Pembayaran & alokasi')

@section('content')
    <div class="page-head">
        <div>
            <h1>Pembayaran online</h1>
            <p>Pantau transaksi gabungan, alokasi per toko, dan pencairan dana penjual.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.stores.index') }}" class="btn btn-outline-brand btn-sm btn-dash"><i class="bi bi-shop me-1"></i> Daftar toko</a>
        </div>
    </div>

    @if (! $integration['configured'])
        <div class="alert alert-danger app-alert">
            <i class="bi bi-shield-exclamation"></i>
            <div>
                <strong>Midtrans belum siap.</strong> {{ $integration['configuration_error'] }}
                Checkout online otomatis dinonaktifkan; pembeli hanya dapat memilih COD.
            </div>
        </div>
    @elseif (! $integration['split_enabled'] && ! $integration['disbursement_enabled'])
        <div class="alert alert-warning app-alert">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <strong>Prasyarat eksternal belum aktif.</strong>
                Akun Midtrans Sikitaya belum memiliki akses Partner transaction split maupun disbursement API.
                Aktifkan salah satu di dashboard Midtrans, lalu isi konfigurasi yang sesuai. Checkout online tetap diblokir
                dan admin tidak boleh menandai dana terkirim secara manual.
            </div>
        </div>
    @endif

    <div class="detail-panel">
        <h2><i class="bi bi-hdd-network"></i> Status integrasi</h2>
        <div class="row g-3">
            <div class="col-sm-6 col-xl-3">
                <div class="border rounded-3 p-3 h-100">
                    <div class="cell-sub">Environment</div>
                    <div class="fw-bold">{{ $integration['production'] ? 'Production' : 'Sandbox' }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="border rounded-3 p-3 h-100">
                    <div class="cell-sub">Transaction split</div>
                    <div class="fw-bold">{{ $integration['split_enabled'] ? 'Aktif' : 'Tidak aktif' }}</div>
                    <div class="cell-sub">{{ $integration['split_ready_count'] }} toko siap</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="border rounded-3 p-3 h-100">
                    <div class="cell-sub">Disbursement API</div>
                    <div class="fw-bold">{{ $integration['disbursement_enabled'] ? 'Aktif' : 'Tidak aktif' }}</div>
                    <div class="cell-sub">{{ $integration['payout_ready_count'] }} rekening terverifikasi</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="border rounded-3 p-3 h-100">
                    <div class="cell-sub">Merchant platform</div>
                    <div class="fw-bold">{{ $integration['platform_merchant_ready'] ? 'Terisi' : 'Belum diisi' }}</div>
                    <div class="cell-sub">Untuk sisa split & biaya</div>
                </div>
            </div>
        </div>
    </div>

    <div class="detail-panel">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h2 class="mb-0"><i class="bi bi-shop"></i> Kesiapan toko</h2>
            <span class="cell-sub">Split memerlukan merchant ID terdaftar. Disbursement memerlukan rekening terverifikasi.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-clean align-middle">
                <thead>
                    <tr>
                        <th>Toko</th>
                        <th>Merchant ID</th>
                        <th>Status merchant</th>
                        <th>Rekening penerima</th>
                        <th>Verifikasi</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($storeRows as $row)
                        @php($store = $row['store'])
                        <tr>
                            <td>
                                <div class="cell-main">{{ $store->name }}</div>
                                <div class="cell-sub">{{ $store->owner?->name }}</div>
                            </td>
                            <td>{{ $store->midtrans_merchant_id ?: '-' }}</td>
                            <td><span class="{{ $store->midtrans_merchant_status?->badgeClass() }}">{{ $store->midtrans_merchant_status?->label() ?? 'Belum terdaftar' }}</span></td>
                            <td>
                                @if ($store->payout_bank_code)
                                    <div>{{ strtoupper($store->payout_bank_code) }} · {{ $store->maskedPayoutAccountNumber() }}</div>
                                    <div class="cell-sub">{{ $store->payout_account_name ?: '-' }}</div>
                                @else
                                    <span class="text-muted">Belum diisi</span>
                                @endif
                            </td>
                            <td>
                                @if ($row['payout_ready'])
                                    <span class="badge-soft green"><i class="bi bi-check2-circle"></i> Terverifikasi</span>
                                @else
                                    <span class="badge-soft red"><i class="bi bi-exclamation-circle"></i> Belum</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.store-payments.edit', $store) }}" class="btn btn-outline-brand btn-sm">Atur</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="filter-tabs">
            <a class="{{ empty($filters['status'] ?? null) ? 'active' : '' }}" href="{{ route('admin.payments.index', array_filter(['method' => $filters['method'] ?? null, 'q' => $filters['q'] ?? null])) }}">
                Semua <span>{{ $statusCounts->sum() }}</span>
            </a>
            @foreach (\App\Enums\PaymentTransactionStatus::cases() as $status)
                <a class="{{ ($filters['status'] ?? null) === $status->value ? 'active' : '' }}" href="{{ route('admin.payments.index', array_filter(['status' => $status->value, 'method' => $filters['method'] ?? null, 'q' => $filters['q'] ?? null])) }}">
                    {{ $status->label() }} <span>{{ $statusCounts[$status->value] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <form action="{{ route('admin.payments.index') }}" method="GET" class="filter-bar">
        @if ($filters['status'] ?? null)
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
        @endif
        <div class="nav-search" style="min-width:260px;">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari referensi, pembeli, atau no. pesanan" aria-label="Cari pembayaran">
            <button type="submit" class="visually-hidden">Cari</button>
        </div>
        <select name="method" class="form-select" style="max-width:220px;" aria-label="Filter metode alokasi">
            <option value="">Semua metode</option>
            @foreach (\App\Enums\PaymentAllocationMethod::cases() as $method)
                <option value="{{ $method->value }}" @selected(($filters['method'] ?? null) === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-brand">Terapkan</button>
        @if (filled($filters['q'] ?? null) || filled($filters['method'] ?? null) || filled($filters['status'] ?? null))
            <a href="{{ route('admin.payments.index') }}" class="btn btn-ghost">Reset</a>
        @endif
        <span class="ms-auto text-muted" style="font-size:.68rem;">{{ $transactions->total() }} transaksi</span>
    </form>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-clean">
                <thead>
                    <tr>
                        <th>Referensi</th>
                        <th>Pembeli</th>
                        <th class="text-center">Pesanan</th>
                        <th class="text-end">Total</th>
                        <th>Alokasi</th>
                        <th class="text-end">Dana penjual</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>
                                <div class="cell-main">{{ $transaction->reference }}</div>
                                <div class="cell-sub">{{ tanggal_indonesia($transaction->created_at, true) }}</div>
                            </td>
                            <td>{{ $transaction->user->name }}</td>
                            <td class="text-center">{{ $transaction->orders_count }}</td>
                            <td class="text-end cell-money">{{ rupiah($transaction->gross_amount) }}</td>
                            <td>
                                <span class="badge-soft"><i class="bi bi-diagram-3"></i> {{ $transaction->allocation_method?->shortLabel() ?? '-' }}</span>
                            </td>
                            <td class="text-end cell-money">{{ rupiah((int) ($transaction->allocations_sum_net_amount ?? 0)) }}</td>
                            <td><span class="{{ $transaction->status->badgeClass() }}">{{ $transaction->status->label() }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.payments.show', $transaction) }}" class="btn-table" title="Lihat detail" aria-label="Lihat {{ $transaction->reference }}"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="empty-state border-0 p-0">
                                    <span class="empty-icon"><i class="bi bi-credit-card"></i></span>
                                    <h2>Belum ada transaksi online</h2>
                                    <p>Transaksi Midtrans akan muncul di sini setelah pembeli checkout.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($transactions->hasPages())
            <div class="table-footer">
                <span class="page-info">Menampilkan {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} dari {{ $transactions->total() }} transaksi</span>
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
@endsection
