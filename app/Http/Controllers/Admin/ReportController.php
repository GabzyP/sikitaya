<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SalesReportExport;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\SalesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private readonly SalesReportService $reports) {}

    public function index(Request $request): View
    {
        $filters = $this->reports->filters($request->only(['from', 'to', 'status', 'store_id']));
        $orders = $this->reports->query($filters)->paginate(15)->withQueryString();
        $summary = $this->reports->summary($filters);
        $stores = Store::orderBy('name')->get();

        return view('admin.reports.index', compact('filters', 'orders', 'summary', 'stores'));
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $filters = $this->reports->filters($request->only(['from', 'to', 'status', 'store_id']));
        $orders = $this->reports->query($filters)->get();

        return Excel::download(
            new SalesReportExport($this->reports->exportRows($orders)),
            'laporan-penjualan-platform-'.$filters['from']->format('Ymd').'-'.$filters['to']->format('Ymd').'.xlsx'
        );
    }

    public function pdf(Request $request): Response
    {
        $filters = $this->reports->filters($request->only(['from', 'to', 'status', 'store_id']));
        $orders = $this->reports->query($filters)->get();
        $summary = $this->reports->summary($filters);
        $store = $filters['store_id'] ? Store::find($filters['store_id']) : null;

        $pdf = Pdf::loadView('exports.sales-pdf', [
            'title' => 'Laporan Penjualan Platform',
            'store' => null,
            'stores' => $store ? collect([$store]) : null,
            'selectedStore' => $store,
            'filters' => $filters,
            'orders' => $orders,
            'summary' => $summary,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('laporan-penjualan-platform-'.Str::slug($store?->name ?? 'semua-toko').'-'.$filters['from']->format('Ymd').'-'.$filters['to']->format('Ymd').'.pdf');
    }
}
