<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogueFilterRequest;
use App\Http\Requests\DistributorRequest;
use App\Models\Distributor;
use App\Models\Product;
use App\Services\CatalogueService;
use App\Services\CsvExportService;
use App\Services\ExcelExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DistributorController extends Controller
{
    public function index(CatalogueFilterRequest $request, CatalogueService $catalogue): View
    {
        $distributors = $catalogue->distributorQuery($request->validated())->paginate((int) ($request->validated('per_page') ?? ($request->input('view') === 'cards' ? 12 : 5)))->withQueryString();

        return view('distributors.index', [
            'distributors' => $distributors, 'types' => $catalogue->options()['types'],
            'categoryCounts' => $catalogue->distributorCategories($distributors->modelKeys()),
            'distributorCount' => Distributor::where('is_active', true)->count(),
            'linkedProductCount' => Product::whereHas('distributors', fn (Builder $distributorQuery) => $distributorQuery->where('is_active', true))->count(),
            'editing' => $request->filled('edit') ? Distributor::findOrFail($request->integer('edit')) : null,
        ]);
    }

    public function store(DistributorRequest $request): RedirectResponse
    {
        Distributor::create($request->validated());

        return redirect()->route('distributors.index')->with('success', 'Distributor baru berhasil ditambahkan.');
    }

    public function update(DistributorRequest $request, Distributor $distributor): RedirectResponse
    {
        $distributor->update($request->validated());

        return redirect()->route('distributors.index')->with('success', 'Distributor berhasil diperbarui.');
    }

    public function export(CatalogueFilterRequest $request, CatalogueService $catalogue, CsvExportService $csv, ExcelExportService $excel): StreamedResponse
    {
        $rows = $catalogue->distributorQuery($request->validated())->lazy(200)->map(fn (Distributor $distributor): array => [$distributor->name, $distributor->company_name, $distributor->phone, $distributor->email, $distributor->city, $distributor->products_count]);
        $headers = ['Nama', 'Perusahaan', 'Telepon', 'Email', 'Kota', 'Total Produk'];

        if ($request->query('format') === 'xlsx' || $request->query('format') === 'excel') {
            return $excel->download(
                'distributor-'.now()->format('Ymd').'.xlsx',
                'DAFTAR DISTRIBUTOR & PEMASOK',
                $headers,
                $rows,
                'Master Data Distributor Suku Cadang'
            );
        }

        return $csv->download('distributor-'.now()->format('Ymd').'.csv', $headers, $rows);
    }
}
