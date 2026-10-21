<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductImportRequest;
use App\Http\Requests\PurchasePlanRequest;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\PurchasePlan;
use App\Models\Shipment;
use App\Models\ShippingService;
use App\Services\ExcelExportService;
use App\Services\ProductImportService;
use App\Services\PurchaseOrderNoteService;
use App\Services\PurchasePlanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchasePlanController extends Controller
{
    public function create(Request $request, PurchaseOrderNoteService $notes): View
    {
        $data = $request->validate(['replan' => ['nullable', 'integer', 'min:1'], 'source_items' => ['required_with:replan', 'array', 'min:1', 'max:100'], 'source_items.*' => ['integer', 'min:0', 'max:99', 'distinct']]);
        $response = $this->editor();
        if (! empty($data['replan'])) {
            $source = PurchasePlan::with(['orders.items', 'orders.invoice.shipment', 'orders.invoice.payments'])->findOrFail($data['replan']);
            $shortages = collect($notes->shortages($source))->keyBy('source_index');
            $items = [];
            foreach ($data['source_items'] as $index) {
                abort_unless($source->status === 'finalized' && $shortages->has($index), 422, 'Produk tidak lagi memiliki kekurangan pada nota. Muat ulang rencana asal.');
                $items[] = array_replace($source->items[$index], ['quantity' => $shortages[$index]['quantity'], 'allocations' => []]);
            }
            $initial = $response->getData()['initial'];
            $initial['name'] = Str::limit('Pengadaan Lanjutan - '.$source->name, 255, '');
            $initial['items'] = $items;
            $response->with('initial', $initial);
        }

        return $response;
    }

    public function edit(PurchasePlan $purchasePlan): View
    {
        abort_if($purchasePlan->status !== 'draft', 409, 'Rencana sudah difinalisasi.');

        return $this->editor($purchasePlan);
    }

    private function editor(?PurchasePlan $plan = null): View
    {
        return view('purchase-orders.plan-create', [
            'plan' => $plan,
            'initial' => ['request_id' => $plan?->request_id ?? (string) Str::uuid(), 'version' => $plan?->version ?? 0, 'name' => $plan?->name ?? '', 'reference' => $plan?->reference, 'planned_on' => $plan?->planned_on->toDateString() ?? now()->toDateString(), 'items' => $plan?->items ?? []],
            'distributors' => Distributor::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'saveUrl' => $plan ? route('purchase-plans.update', $plan) : route('purchase-plans.store'),
        ]);
    }

    public function store(PurchasePlanRequest $request, PurchasePlanService $service): JsonResponse
    {
        return $this->saved($service->save($request->validated()));
    }

    public function update(PurchasePlanRequest $request, PurchasePlan $purchasePlan, PurchasePlanService $service): JsonResponse
    {
        return $this->saved($service->save($request->validated(), $purchasePlan));
    }

    private function saved(PurchasePlan $plan): JsonResponse
    {
        session()->flash('success', $plan->status === 'draft' ? 'Draft rencana berhasil disimpan.' : 'Rencana difinalisasi. PO dibuat sesuai alokasi distributor.');

        return response()->json(['redirect' => $plan->status === 'draft' ? route('purchase-plans.edit', $plan) : route('purchase-plans.show', $plan)]);
    }

    public function show(PurchasePlan $purchasePlan, PurchaseOrderNoteService $notes): View
    {
        $purchasePlan->load(['orders.items', 'orders.invoice.shipment', 'orders.invoice.payments']);

        return view('purchase-orders.plan-show', ['plan' => $purchasePlan, 'orders' => $notes->snapshots($purchasePlan), 'shippingServices' => ShippingService::where('is_active', true)->orderBy('name')->get(['id', 'name', 'duration_days']), 'shippingToday' => Shipment::today()->toDateString()]);
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $products = Product::with(['brand', 'type', 'color', 'stock'])->where('is_active', true);
        if (! empty($data['search'])) {
            $products->where(function (Builder $query) use ($data): void {
                $query->where('name', 'like', '%'.$data['search'].'%')->orWhere('sku', 'like', '%'.$data['search'].'%')->orWhere('barcode', 'like', '%'.$data['search'].'%');
            });
        }

        return response()->json($products->orderBy('name')->limit(20)->get()->map(fn (Product $product): array => $this->productRow($product)));
    }

    /** @return array<string, mixed> */
    private function productRow(Product $product): array
    {
        return ['product_id' => $product->id, 'product_name' => $product->name, 'color' => $product->color?->name ?? '', 'product_kind' => $product->product_kind, 'brand' => $product->brand?->name ?? '', 'part_type' => $product->type?->name ?? '', 'current_stock' => $product->stock?->quantity ?? 0, 'quantity' => 0, 'sku' => $product->sku ?? '', 'unit' => $product->unit ?: 'pcs', 'unit_price' => $product->purchase_price ?? 0, 'allocations' => []];
    }

    public function template(ExcelExportService $excel): StreamedResponse
    {
        return $excel->download('template-rencana-pengadaan.xlsx', 'RENCANA PENGADAAN', ['Nama Produk', 'Warna', 'Tipe Produk', 'Merk', 'Jenis Part', 'Kebutuhan', 'SKU', 'Modal'], [], 'Tipe Produk: daily, non_daily, variation. Kebutuhan adalah jumlah unit yang akan dipesan.', forImport: true);
    }

    public function import(ProductImportRequest $request, ProductImportService $importer): JsonResponse
    {
        $rows = $importer->readForPlanning($request->file('file'));
        $items = [];
        foreach ($rows as $row) {
            $items[] = ['product_id' => null, 'product_name' => $row['name'] ?? '', 'color' => $row['color_name'] ?? '', 'product_kind' => match (mb_strtolower($row['product_kind'] ?? 'daily')) {
                'harian' => 'daily', 'non-harian', 'non harian' => 'non_daily', 'variasi' => 'variation', default => $row['product_kind'] ?? 'daily'
            }, 'brand' => $row['brand_name'] ?? '', 'part_type' => $row['type_name'] ?? '', 'current_stock' => 0, 'quantity' => $row['quantity'] ?? 0, 'sku' => $row['sku'] ?? '', 'unit' => 'pcs', 'unit_price' => $row['purchase_price'] ?? 0, 'allocations' => []];
        }
        $validation = new PurchasePlanRequest;
        $rules = collect($validation->rules())->filter(fn (mixed $rule, string $key): bool => $key === 'items' || str_starts_with($key, 'items.'))->all();
        Validator::make(['items' => $items], $rules)->validate();

        return response()->json(['items' => $items]);
    }
}
