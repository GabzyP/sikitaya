<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'No',
            'No. Pesanan',
            'Tanggal',
            'Toko',
            'Pembeli',
            'Status',
            'Pembayaran',
            'Jumlah Item',
            'Subtotal (Rp)',
            'Ongkir (Rp)',
            'Total (Rp)',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rows->count() + 1;

        $sheet->getStyle('A1:K1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:K1')->getFill()->setFillType('solid')->getStartColor()->setRGB('0B6B53');
        $sheet->getStyle('A1:K'.$lastRow)->getAlignment()->setVertical('center');
        $sheet->getStyle('I2:K'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->freezePane('A2');

        return [];
    }

    public function title(): string
    {
        return 'Laporan Penjualan';
    }
}
