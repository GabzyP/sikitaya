<?php

namespace App\Http\Controllers\Seller;

use App\Exports\SalesReportExport;
use App\Http\Controllers\Controller;
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
        $store = auth()->user()->store;
        $filters = $this->reports->filters($request->only(['from', 'to', 'status']), $store->id);
        $orders = $this->reports->query($filters, $store->id)->paginate(15)->withQueryString();
        $summary = $this->reports->summary($filters, $store->id);

        return view('seller.reports.index', compact('store', 'filters', 'orders', 'summary'));
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $store = auth()->user()->store;
        $filters = $this->reports->filters($request->only(['from', 'to', 'status']), $store->id);
        $orders = $this->reports->query($filters, $store->id)->get();

        return Excel::download(
            new SalesReportExport($this->reports->exportRows($orders)),
            'laporan-penjualan-'.Str::slug($store->name).'-'.$filters['from']->format('Ymd').'-'.$filters['to']->format('Ymd').'.xlsx'
        );
    }

    public function pdf(Request $request): Response
    {
        $store = auth()->user()->store;
        $filters = $this->reports->filters($request->only(['from', 'to', 'status']), $store->id);
        $orders = $this->reports->query($filters, $store->id)->get();
        $summary = $this->reports->summary($filters, $store->id);

        $pdf = Pdf::loadView('exports.sales-pdf', [
            'title' => 'Laporan Penjualan',
            'store' => $store,
            'stores' => null,
            'filters' => $filters,
            'orders' => $orders,
            'summary' => $summary,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('laporan-penjualan-'.Str::slug($store->name).'-'.$filters['from']->format('Ymd').'-'.$filters['to']->format('Ymd').'.pdf');
    }
}
