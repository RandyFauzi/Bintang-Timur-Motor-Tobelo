<?php

namespace App\Http\Controllers;

use App\Http\Requests\DistributorInvoiceRequest;
use App\Http\Requests\DistributorPaymentRequest;
use App\Http\Requests\ImportDistributorInvoiceRequest;
use App\Http\Requests\StoreDistributorInvoiceRequest;
use App\Models\Distributor;
use App\Models\DistributorInvoice;
use App\Models\Shipment;
use App\Services\DistributorInvoiceImportService;
use App\Services\DistributorInvoiceService;
use App\Services\ExcelExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DistributorInvoiceController extends Controller
{
    public function __construct()
    {
        DistributorInvoiceService::ensureSchema();
    }

    public function index(DistributorInvoiceRequest $request): View
    {
        $filters = $request->validated();
        $base = DistributorInvoice::query();
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $base->where(fn (Builder $q): Builder => $q->where('reference', 'like', $term)
                ->orWhere('supplier_reference', 'like', $term)
                ->orWhereHas('purchaseOrder', fn (Builder $q): Builder => $q->where('distributor_name', 'like', $term)->orWhere('reference', 'like', $term))
                ->orWhereHas('distributor', fn (Builder $q): Builder => $q->where('name', 'like', $term))
            );
        }
        if (! empty($filters['distributor_id'])) {
            $base->where(fn (Builder $q): Builder => $q->where('distributor_id', $filters['distributor_id'])
                ->orWhereHas('purchaseOrder', fn (Builder $q): Builder => $q->where('distributor_id', $filters['distributor_id']))
            );
        }
        $today = DistributorInvoice::today();
        $summary = ['total' => (int) (clone $base)->sum('total_cents'), 'paid' => (int) (clone $base)->sum('paid_cents'), 'outstanding' => (int) (clone $base)->sum(DB::raw('total_cents - paid_cents')), 'overdue' => (int) (clone $base)->outstanding()->whereDate('due_on', '<=', $today->toDateString())->sum(DB::raw('total_cents - paid_cents'))];
        if (! empty($filters['status'])) {
            $base->where(function (Builder $statuses) use ($filters, $today): void {
                foreach ($filters['status'] as $status) {
                    $statuses->orWhere(function (Builder $query) use ($status, $today): void {
                        match ($status) {
                            'paid' => $query->whereColumn('paid_cents', '>=', 'total_cents'),
                            'unpaid' => $query->outstanding()->where('paid_cents', 0),
                            'partial' => $query->outstanding()->where('paid_cents', '>', 0),
                            'overdue' => $query->outstanding()->whereDate('due_on', '<=', $today->toDateString()),
                        };
                    });
                }
            });
        }
        $timing = (string) ($filters['timing'] ?? '');
        if ($timing === '1') {
            $base->whereDate('due_on', '>=', $today->toDateString())
                ->whereDate('due_on', '<=', $today->addMonthsNoOverflow(1)->toDateString());
        } elseif ($timing === '2') {
            $base->whereDate('due_on', '>', $today->addMonthsNoOverflow(1)->toDateString())
                ->whereDate('due_on', '<=', $today->addMonthsNoOverflow(2)->toDateString());
        } elseif ($timing === '3') {
            $base->whereDate('due_on', '>', $today->addMonthsNoOverflow(2)->toDateString())
                ->whereDate('due_on', '<=', $today->addMonthsNoOverflow(3)->toDateString());
        } elseif ($timing === '4') {
            $base->whereDate('due_on', '>', $today->addMonthsNoOverflow(3)->toDateString())
                ->whereDate('due_on', '<=', $today->addMonthsNoOverflow(4)->toDateString());
        } elseif ($timing === 'overdue') {
            $base->outstanding()->whereDate('due_on', '<=', $today->toDateString());
        } elseif ($timing === 'near') {
            $base->needsReminder()->whereDate('due_on', '>=', $today->toDateString());
        }

        return view('distributor-invoices.index', [
            'invoices' => $base->with(['purchaseOrder', 'distributor', 'shipment'])->orderByRaw('paid_cents >= total_cents')->orderBy('due_on')->orderBy('id')->paginate(15)->withQueryString(),
            'distributors' => Distributor::orderBy('name')->get(),
            'shipments' => Shipment::orderByDesc('id')->get(['id', 'reference', 'cargo_name', 'delivered_at']),
            'summary' => $summary,
        ]);
    }

    public function store(StoreDistributorInvoiceRequest $request, DistributorInvoiceService $billing): RedirectResponse
    {
        $invoice = $billing->createManual($request->validated());

        return redirect()->route('distributor-invoices.index')
            ->with('success', 'Tagihan manual '.$invoice->reference.' berhasil ditambahkan.');
    }

    public function template(ExcelExportService $excel): StreamedResponse
    {
        return $excel->download(
            'template-import-tagihan-distributor.xlsx',
            'TEMPLATE IMPORT TAGIHAN DISTRIBUTOR',
            DistributorInvoiceImportService::HEADERS,
            [],
            'Petunjuk: Kolom Distributor, Jatuh Tempo, dan Total Tagihan wajib diisi. Nomor invoice otomatis jika dikosongkan.',
            forImport: true
        );
    }

    public function import(ImportDistributorInvoiceRequest $request, DistributorInvoiceImportService $importer): RedirectResponse
    {
        $count = $importer->import($request->file('file'));

        return redirect()->route('distributor-invoices.index')
            ->with('success', $count.' tagihan distributor berhasil diimpor.');
    }

    public function show(DistributorInvoice $distributorInvoice): View
    {
        return view('distributor-invoices.show', [
            'invoice' => $distributorInvoice->load(['purchaseOrder.items', 'distributor', 'payments.creator', 'shipment.receiver']),
            'requestId' => (string) Str::uuid(),
            'distributors' => Distributor::orderBy('name')->get(),
            'shipments' => Shipment::orderByDesc('id')->get(['id', 'reference', 'cargo_name', 'delivered_at']),
        ]);
    }

    public function update(DistributorInvoiceRequest $request, DistributorInvoice $distributorInvoice, DistributorInvoiceService $billing): RedirectResponse
    {
        $billing->updateDetails($distributorInvoice, $request->validated());

        return back(fallback: route('distributor-invoices.show', $distributorInvoice))
            ->with('success', 'Informasi tagihan '.$distributorInvoice->fresh()->reference.' berhasil diperbarui.');
    }

    public function pay(DistributorPaymentRequest $request, DistributorInvoice $distributorInvoice, DistributorInvoiceService $billing): RedirectResponse
    {
        $billing->pay($distributorInvoice, $request->validated(), $request->user());

        return redirect()->route('distributor-invoices.show', $distributorInvoice)->with('success', 'Pembayaran berhasil dicatat. Sisa tagihan diperbarui otomatis.');
    }

    public function destroy(Request $request, DistributorInvoice $distributorInvoice, DistributorInvoiceService $billing): RedirectResponse
    {
        $reference = $distributorInvoice->reference;
        $version = $request->filled('version') ? $request->integer('version') : null;
        $billing->delete($distributorInvoice, $version);

        return redirect()->route('distributor-invoices.index')->with('success', 'Tagihan '.$reference.' berhasil dihapus.');
    }
}
