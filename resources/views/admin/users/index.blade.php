@extends('layouts.dashboard')

@section('title', 'Pengguna')
@section('eyebrow', 'Manajemen')
@section('page_title', 'Pengguna')

@section('content')
    <div class="page-head">
        <div>
            <h1>Pengguna platform</h1>
            <p>Kelola akun admin, penjual, dan pembeli Sikitaya.</p>
        </div>
    </div>

    <form action="{{ route('admin.users.index') }}" method="GET" class="filter-bar">
        <div class="nav-search" style="min-width:250px;">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama atau email..." aria-label="Cari pengguna">
            <button type="submit" class="visually-hidden">Cari</button>
        </div>
        <select name="role" class="form-select" style="max-width:160px;" aria-label="Filter peran">
            <option value="">Semua peran</option>
            @foreach (\App\Enums\UserRole::cases() as $role)
                <option value="{{ $role->value }}" @selected(($filters['role'] ?? null) === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
        <select name="status" class="form-select" style="max-width:160px;" aria-label="Filter status">
            <option value="">Semua status</option>
            <option value="active" @selected(($filters['status'] ?? null) === 'active')>Aktif</option>
            <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Nonaktif</option>
        </select>
        <button type="submit" class="btn btn-brand">Terapkan</button>
        @if (filled($filters['q'] ?? null) || filled($filters['role'] ?? null) || filled($filters['status'] ?? null))
            <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Reset</a>
        @endif
        <span class="ms-auto text-muted" style="font-size:.68rem;">{{ $users->total() }} pengguna</span>
    </form>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-clean">
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Peran</th>
                        <th>Toko</th>
                        <th>Kontak</th>
                        <th>Terdaftar</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <span class="avatar-xs">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
                                    <div>
                                        <div class="cell-main">{{ $user->name }}</div>
                                        <div class="cell-sub">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($user->isAdmin())
                                    <span class="badge-soft purple" style="background:#f0e9fa;color:#6b3fa0;"><i class="bi bi-shield-check"></i> Admin</span>
                                @elseif ($user->isSeller())
                                    <span class="badge-soft green"><i class="bi bi-shop"></i> Penjual</span>
                                @else
                                    <span class="badge-soft blue"><i class="bi bi-bag"></i> Pembeli</span>
                                @endif
                            </td>
                            <td>
                                @if ($user->store)
                                    <a href="{{ route('stores.show', $user->store) }}" target="_blank" class="cell-main text-decoration-none">{{ $user->store->name }}</a>
                                    <div class="cell-sub">{{ $user->store->city_name }}</div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $user->phone ?: '-' }}</td>
                            <td>{{ $user->created_at->format('d/m/Y') }}</td>
                            <td>
                                @if ($user->is_active)
                                    <span class="badge-soft green"><i class="bi bi-check-circle"></i> Aktif</span>
                                @else
                                    <span class="badge-soft red"><i class="bi bi-x-circle"></i> Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                    @if ($user->isAdmin())
                                        <span class="badge-soft" title="Akun admin tidak dapat dinonaktifkan"><i class="bi bi-shield-lock"></i> Terlindungi</span>
                                    @else
                                        <form action="{{ route('admin.users.toggle', $user) }}" method="POST" onsubmit="return confirm('{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ addslashes($user->name) }}?')">
                                            @csrf
                                            <button type="submit" class="btn-table {{ $user->is_active ? 'danger' : '' }}" title="{{ $user->is_active ? 'Nonaktifkan akun' : 'Aktifkan akun' }}" aria-label="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $user->name }}">
                                                <i class="bi {{ $user->is_active ? 'bi-person-slash' : 'bi-person-check' }}"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state border-0 p-0">
                                    <span class="empty-icon"><i class="bi bi-people"></i></span>
                                    <h2>Tidak ada pengguna</h2>
                                    <p>Tidak ada pengguna yang cocok dengan filter Anda.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="table-footer">
                <span class="page-info">Menampilkan {{ $users->firstItem() }}–{{ $users->lastItem() }} dari {{ $users->total() }} pengguna</span>
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
