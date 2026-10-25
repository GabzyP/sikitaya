@if (session('success') || session('error') || session('warning') || $errors->any())
    <div class="container mt-3">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show app-alert" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show app-alert" role="alert">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show app-alert" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>{{ session('warning') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show app-alert" role="alert">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div>
                    <strong>Periksa kembali data Anda.</strong>
                    <ul class="mb-0 ps-3 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        @endif
    </div>
@endif
