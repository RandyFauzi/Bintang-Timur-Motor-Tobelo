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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public function store(DistributorRequest $request): RedirectResponse|JsonResponse
    {
        $distributor = Distributor::create($request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Distributor baru berhasil ditambahkan.',
                'data' => [
                    'id' => $distributor->id,
                    'name' => $distributor->name,
                ],
            ], 201);
        }

        return redirect()->route('distributors.index')->with('success', 'Distributor baru berhasil ditambahkan.');
    }

    public function update(DistributorRequest $request, Distributor $distributor): RedirectResponse
    {
        $distributor->update($request->validated());

        return redirect()->route('distributors.index')->with('success', 'Distributor berhasil diperbarui.');
    }

    public function destroy(Distributor $distributor): RedirectResponse
    {
        if ($distributor->purchaseOrders()->whereNull('archived_at')->whereHas('items', fn ($q) => $q->whereNull('actual_quantity'))->exists()) {
            throw ValidationException::withMessages([
                'distributor' => 'Distributor '.$distributor->name.' tidak dapat dihapus karena masih memiliki pengadaan barang yang sedang berjalan. Selesaikan pemeriksaan atau arsipkan pengadaan terlebih dahulu.',
            ]);
        }

        DB::transaction(function () use ($distributor): void {
            $distributor->is_active = false;
            $distributor->save();
            $distributor->delete();
        });

        return redirect()->route('distributors.index')->with('success', 'Distributor '.$distributor->name.' berhasil dihapus.');
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
