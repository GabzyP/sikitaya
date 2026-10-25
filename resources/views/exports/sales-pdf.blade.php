@php
    $scopeLabel = $store?->name ?? ($selectedStore?->name ?? 'Semua Toko');
    $statusLabel = $filters['status']?->label() ?? 'Semua Status';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 24px 28px; }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5px;
            color: #1f2d27;
            margin: 0;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 3px;
            color: #0d2b23;
        }

        .subtitle {
            font-size: 9px;
            color: #5c6b64;
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #0b6b53;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .header table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: bottom; }
        .header .right { text-align: right; }
        .brand {
            font-size: 11px;
            font-weight: bold;
            color: #0b6b53;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8px;
        }

        .meta td { padding: 2px 0; color: #4c5b54; }
        .meta td.label { width: 100px; color: #7a8881; }

        .summary {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin: 0 -6px 12px;
        }

        .summary td {
            width: 25%;
            background: #f3f7f4;
            border: 1px solid #dbe7e0;
            padding: 7px 8px;
        }

        .summary .kpi-label {
            display: block;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #708078;
            margin-bottom: 3px;
        }

        .summary .kpi-value {
            font-size: 12px;
            font-weight: bold;
            color: #0d2b23;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
        }

        table.report thead th {
            background: #0b6b53;
            color: #ffffff;
            font-size: 7.2px;
            text-transform: uppercase;
            letter-spacing: .3px;
            padding: 6px 5px;
            text-align: left;
            border: 1px solid #0b6b53;
        }

        table.report tbody td {
            padding: 5px;
            border: 1px solid #e0e7e3;
            vertical-align: top;
            font-size: 7.8px;
        }

        table.report tbody tr:nth-child(even) td { background: #fafcfb; }
        table.report tbody tr { page-break-inside: avoid; }
        table.report .text-center { text-align: center; }
        table.report .text-end { text-align: right; }
        table.report .money { white-space: nowrap; }
        table.report tfoot td {
            background: #eef5f1;
            border: 1px solid #dbe7e0;
            padding: 6px 5px;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            padding: 22px;
            color: #708078;
            border: 1px solid #e0e7e3;
        }

        .footer {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid #dbe7e0;
            font-size: 7px;
            color: #84918b;
        }

        .footer .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="brand">SIKITAYA · UMKM KOTA MEDAN</div>
                    <h1>{{ $title }}</h1>
                    <p class="subtitle">Rekap transaksi penjualan {{ $scopeLabel }}</p>
                </td>
                <td class="right">
                    <p class="subtitle">Dicetak {{ tanggal_indonesia(now(), true) }}</p>
                    <p class="subtitle">Periode {{ $filters['from']->format('d/m/Y') }} – {{ $filters['to']->format('d/m/Y') }}</p>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Lingkup laporan</td>
            <td>: {{ $scopeLabel }}</td>
            <td class="label">Status pesanan</td>
            <td>: {{ $statusLabel }}</td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>
                <span class="kpi-label">Total Pesanan</span>
                <span class="kpi-value">{{ number_format($summary['orders'], 0, ',', '.') }}</span>
            </td>
            <td>
                <span class="kpi-label">Item Terjual</span>
                <span class="kpi-value">{{ number_format($summary['items'], 0, ',', '.') }}</span>
            </td>
            <td>
                <span class="kpi-label">Omzet Terverifikasi</span>
                <span class="kpi-value">{{ rupiah($summary['omzet']) }}</span>
            </td>
            <td>
                <span class="kpi-label">Menunggu</span>
                <span class="kpi-value">{{ number_format($summary['pending'], 0, ',', '.') }}</span>
            </td>
        </tr>
    </table>

    @if ($orders->isEmpty())
        <div class="empty">Tidak ada transaksi pada periode dan filter yang dipilih.</div>
    @else
        <table class="report">
            <thead>
                <tr>
                    <th style="width:3%;">No</th>
                    <th style="width:12%;">No. Pesanan</th>
                    <th style="width:8%;">Tanggal</th>
                    <th style="width:13%;">Toko</th>
                    <th style="width:13%;">Pembeli</th>
                    <th style="width:7%;">Status</th>
                    <th style="width:6%;">Bayar</th>
                    <th style="width:5%;" class="text-center">Item</th>
                    <th style="width:10%;" class="text-end">Subtotal</th>
                    <th style="width:8%;" class="text-end">Ongkir</th>
                    <th style="width:10%;" class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $index => $order)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $order->order_number }}</td>
                        <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $order->store->name }}</td>
                        <td>{{ $order->user->name }}</td>
                        <td>{{ $order->status->label() }}</td>
                        <td>{{ $order->payment_method->shortLabel() }}</td>
                        <td class="text-center">{{ $order->items->sum('quantity') }}</td>
                        <td class="text-end money">{{ rupiah($order->subtotal) }}</td>
                        <td class="text-end money">{{ rupiah($order->shipping_cost) }}</td>
                        <td class="text-end money">{{ rupiah($order->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" class="text-end">Total {{ number_format($summary['orders'], 0, ',', '.') }} pesanan</td>
                    <td class="text-center">{{ number_format($summary['items'], 0, ',', '.') }}</td>
                    <td class="text-end money">{{ rupiah($orders->sum('subtotal')) }}</td>
                    <td class="text-end money">{{ rupiah($orders->sum('shipping_cost')) }}</td>
                    <td class="text-end money">{{ rupiah($orders->sum('total')) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="footer">
        <table style="width:100%;">
            <tr>
                <td>Sikitaya · Marketplace UMKM Kota Medan · Laporan dibuat otomatis oleh sistem.</td>
                <td class="right">Omzet mencakup pesanan berstatus dibayar, dikirim, dan selesai.</td>
            </tr>
        </table>
    </div>
</body>
</html>
