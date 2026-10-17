<?php

namespace App\Http\Controllers;

use App\Http\Requests\DistributorRequest;
use App\Http\Requests\PurchaseOrderReceiptRequest;
use App\Http\Requests\PurchaseOrderRequest;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\DistributorInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'distributor_id' => ['nullable', 'integer']]);
        $query = PurchaseOrder::with('items')->latest('id');
        if (! empty($filters['search'])) {
            $query->where(fn (Builder $q): Builder => $q->where('reference', 'like', '%'.$filters['search'].'%')->orWhere('distributor_name', 'like', '%'.$filters['search'].'%'));
        }
        if (! empty($filters['distributor_id'])) {
            $query->where('distributor_id', $filters['distributor_id']);
        }

        return view('purchase-orders.index', ['orders' => $query->paginate(15)->withQueryString(), 'distributors' => Distributor::where('is_active', true)->orderBy('name')->get()]);
    }

    public function create(Request $request): View
    {
        $rows = collect(is_array(old('items')) ? old('items') : [])->filter(fn (mixed $row): bool => is_array($row) && is_scalar($row['product_id'] ?? null));
        $products = Product::whereIn('id', $rows->pluck('product_id'))->get()->keyBy('id');
        $initialItems = $rows->filter(fn (mixed $row): bool => isset($products[$row['product_id']]))->map(fn (array $row): array => ['product_id' => $row['product_id'], 'name' => $products[$row['product_id']]->name, 'sku' => $products[$row['product_id']]->sku, 'quantity' => is_scalar($row['quantity'] ?? null) ? $row['quantity'] : 1, 'unit_price' => is_scalar($row['unit_price'] ?? null) ? $row['unit_price'] : 0])->values();

        return view('purchase-orders.create', ['distributors' => Distributor::where('is_active', true)->orderBy('name')->get(), 'requestId' => (string) Str::uuid(), 'initialItems' => $initialItems, 'selectedDistributor' => is_scalar(old('distributor_id')) ? old('distributor_id') : $request->integer('distributor_id')]);
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255']]);

        return response()->json(['data' => Product::where('is_active', true)->where(fn (Builder $q): Builder => $q->where('name', 'like', '%'.($data['search'] ?? '').'%')->orWhere('sku', 'like', '%'.($data['search'] ?? '').'%'))->orderBy('name')->limit(20)->get(['id', 'name', 'sku', 'unit', 'purchase_price'])]);
    }

    public function store(PurchaseOrderRequest $request, DistributorInvoiceService $billing): RedirectResponse
    {
        $data = $request->validated();
        $order = DB::transaction(function () use ($data, $billing): PurchaseOrder {
            $distributor = Distributor::lockForUpdate()->findOrFail($data['distributor_id']);
            $existing = PurchaseOrder::where('request_id', $data['request_id'])->first();
            if ($existing) {
                $matches = $existing->distributor_id === (int) $data['distributor_id']
                    && $existing->ordered_on->toDateString() === $data['ordered_on']
                    && $existing->expected_on?->toDateString() === ($data['expected_on'] ?? null)
                    && $existing->notes === ($data['notes'] ?? null)
                    && $existing->invoice?->due_on->toDateString() === ($data['due_on'] ?? CarbonImmutable::parse($data['ordered_on'])->addMonthsNoOverflow((int) ($data['payment_term_months'] ?? 3))->toDateString())
                    && $existing->items()->count() === count($data['items']);
                $existingItems = $existing->items->keyBy('product_id');
                foreach ($data['items'] as $item) {
                    $saved = $existingItems->get($item['product_id']);
                    $matches = $matches && $saved !== null && $saved->quantity === (int) $item['quantity']
                        && DistributorInvoiceService::cents($saved->unit_price) === DistributorInvoiceService::cents($item['unit_price']);
                }
                if (! $matches) {
                    throw ValidationException::withMessages(['request_id' => 'PO ini sudah tersimpan dengan isi berbeda. Buat PO baru.']);
                }

                return $existing;
            }

            $order = PurchaseOrder::create(collect($data)->except(['items', 'due_on', 'payment_term_months'])->all() + ['reference' => 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)), 'distributor_name' => $distributor->name]);
            $products = Product::whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');
            foreach ($data['items'] as $item) {
                $product = $products[$item['product_id']];
                $order->items()->create($item + ['product_name' => $product->name, 'sku' => $product->sku, 'unit' => $product->unit]);
            }

            $billing->createForOrder($order, $data['due_on'] ?? null, (int) ($data['payment_term_months'] ?? 3));

            return $order;
        });

        return redirect()->route('purchase-orders.show', $order)->with('success', 'PO berhasil dibuat.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['items', 'invoice']);
        $oldItems = collect(is_array(old('items')) ? old('items') : [])->filter(fn (mixed $item): bool => is_array($item) && is_scalar($item['id'] ?? null))->keyBy('id');
        $receiptItems = $purchaseOrder->items->map(function (PurchaseOrderItem $item) use ($oldItems): array {
            $old = $oldItems->get($item->id);
            $actual = $old !== null ? ($old['actual_quantity'] ?? null) : $item->actual_quantity;

            return ['quantity' => $item->quantity, 'actual' => is_scalar($actual) ? $actual : null];
        });

        return view('purchase-orders.show', ['order' => $purchaseOrder, 'receiptItems' => $receiptItems]);
    }

    public function receive(PurchaseOrderReceiptRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        DB::transaction(function () use ($request, $purchaseOrder): void {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($order->version !== $request->integer('version')) {
                throw ValidationException::withMessages(['version' => 'PO telah diperbarui pengguna lain. Muat ulang halaman sebelum menyimpan.']);
            }
            foreach ($request->validated('items') as $item) {
                $order->items()->findOrFail($item['id'])->update(['actual_quantity' => $item['actual_quantity']]);
            }
            $order->increment('version');
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Jumlah aktual dan selisih barang berhasil disimpan.');
    }

    public function distributor(DistributorRequest $request): RedirectResponse
    {
        $distributor = Distributor::create($request->validated());

        return redirect()->route('purchase-orders.create', ['distributor_id' => $distributor->id])->with('success', 'Distributor ditambahkan. Silakan buat PO.');
    }
}
