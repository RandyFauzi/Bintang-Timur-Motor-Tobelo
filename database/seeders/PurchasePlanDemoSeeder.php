<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\PurchasePlan;
use App\Models\Shipment;
use App\Models\ShippingService;
use App\Models\User;
use App\Services\DistributorInvoiceService;
use App\Services\PurchaseOrderNoteService;
use App\Services\PurchasePlanService;
use App\Services\ShipmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchasePlanDemoSeeder extends Seeder
{
    private const REQUEST_ID = 'd3f0a501-5520-4c65-9cf0-000000000001';

    public function run(PurchasePlanService $plans, PurchaseOrderNoteService $notes, ShipmentService $tracking, DistributorInvoiceService $billing): void
    {
        DB::transaction(function () use ($plans, $notes, $tracking, $billing): void {
            if (PurchasePlan::where('request_id', self::REQUEST_ID)->exists()) {
                $this->command?->info('Simulasi PO sudah ada. Data yang telah Anda ubah tidak ditimpa.');

                return;
            }
            $user = User::orderByRaw("role = 'admin' desc")->orderBy('id')->firstOrFail();
            $brand = Brand::firstOrCreate(['slug' => 'demo-po-sparepart'], ['name' => '[DEMO] Sparepart Motor']);
            $type = ProductType::firstOrCreate(['slug' => 'demo-po-kebutuhan'], ['name' => '[DEMO] Kebutuhan Pengadaan']);
            $distributors = [];
            foreach ([
                ['Surya Motor', 'Surabaya', 'Jawa Timur'],
                ['Nusantara Sparepart', 'Jakarta', 'DKI Jakarta'],
                ['Sulawesi Motor', 'Makassar', 'Sulawesi Selatan'],
                ['Borneo Suku Cadang', 'Banjarmasin', 'Kalimantan Selatan'],
                ['Manado Motor', 'Manado', 'Sulawesi Utara'],
            ] as [$name, $city, $province]) {
                $distributors[] = Distributor::firstOrCreate(['name' => '[DEMO] '.$name.' '.$city], [
                    'company_name' => '[DEMO] '.$name, 'city' => $city, 'province' => $province,
                    'address' => 'Alamat simulasi di '.$city.'. Bukan pemasok nyata.',
                    'notes' => 'Data dummy untuk simulasi PO 5 distributor dan 15 produk.', 'is_active' => true,
                ]);
            }
            $items = [];
            foreach ($this->products() as $index => [$name, $price, $unit]) {
                $product = Product::firstOrCreate(['sku' => 'DEMO-PO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)], [
                    'name' => '[DEMO] '.$name, 'brand_id' => $brand->id, 'type_id' => $type->id,
                    'purchase_price' => $price, 'selling_price' => $price + 10000, 'unit' => $unit,
                    'stock_minimum' => 0, 'is_active' => true, 'product_kind' => 'daily',
                    'description' => 'Barang dummy untuk preview pengadaan. Tidak mencatat stok masuk.',
                ]);
                $allocations = [];
                foreach ($distributors as $supplierIndex => $distributor) {
                    $product->distributors()->syncWithoutDetaching([$distributor->id => [
                        'distributor_sku' => $product->sku.'-D'.($supplierIndex + 1),
                        'purchase_price' => $price, 'is_primary' => $supplierIndex === 0,
                    ]]);
                    $allocations[] = ['distributor_id' => $distributor->id, 'quantity' => 10 + $index];
                }
                $items[] = [
                    'product_id' => $product->id, 'product_name' => $product->name, 'color' => '',
                    'product_kind' => 'daily', 'brand' => $brand->name, 'part_type' => $type->name,
                    'current_stock' => $product->stock?->quantity ?? 0, 'quantity' => (10 + $index) * 5,
                    'sku' => $product->sku, 'unit' => $unit, 'unit_price' => $price, 'allocations' => $allocations,
                ];
            }
            $today = Shipment::today();
            $plan = $plans->save([
                'request_id' => self::REQUEST_ID, 'version' => 0, 'intent' => 'finalize',
                'name' => '[DEMO] Simulasi PO 5 Distributor - 15 Jenis Barang',
                'planned_on' => $today->subDays(7)->toDateString(), 'items' => $items,
            ]);
            $carrier = ShippingService::firstOrCreate(['name' => '[DEMO] Cargo Antar Pulau'], ['duration_days' => 14, 'is_active' => true]);
            foreach ($distributors as $supplierIndex => $distributor) {
                if ($supplierIndex === 2) {
                    continue;
                }
                $order = $plan->orders()->where('distributor_id', $distributor->id)->sole();
                $rows = [];
                $totalRupiah = 0;
                foreach ($order->items()->orderBy('id')->get() as $index => $item) {
                    $quantity = $item->quantity;
                    if ($supplierIndex === 0 && $index % 4 === 0) {
                        $quantity -= 2;
                    }
                    if ($supplierIndex === 1 && $index === 0) {
                        $quantity = 0;
                    }
                    $rows[] = ['id' => $item->id, 'invoice_quantity' => $quantity];
                    $totalRupiah += $quantity * (int) $item->unit_price;
                }
                $notes->save($order, [
                    'version' => $order->version, 'note_issued' => true,
                    'supplier_note_number' => 'DEMO-NOTA-'.str_pad((string) ($supplierIndex + 1), 2, '0', STR_PAD_LEFT),
                    'supplier_note_on' => $today->subDays(5)->toDateString(),
                    'supplier_note_total' => (string) $totalRupiah, 'items' => $rows,
                ]);
                if ($supplierIndex >= 3) {
                    $shipmentCarrier = $supplierIndex === 4
                        ? ShippingService::firstOrCreate(['name' => '[DEMO] Cargo Sulawesi Utara'], ['duration_days' => 2, 'is_active' => true])
                        : $carrier;
                    $notes->finalize($order->refresh(), [
                        'version' => $order->version, 'shipping_service_id' => $shipmentCarrier->id,
                        'origin_port' => $distributor->city, 'departed_on' => $today->subDays(3)->toDateString(),
                    ], $user, $tracking);
                }
                if ($supplierIndex === 4) {
                    $invoice = $order->fresh()->invoice;
                    $tracking->arrive($invoice->shipment, $user, $invoice->shipment->version);
                    $amount = intdiv($invoice->total_cents, 2);
                    $billing->pay($invoice, [
                        'request_id' => 'd3f0a501-5520-4c65-9cf0-000000000002', 'version' => $invoice->version,
                        'amount' => sprintf('%d.%02d', intdiv($amount, 100), $amount % 100),
                        'paid_on' => $today->toDateString(), 'method' => 'transfer',
                        'reference' => 'DEMO-PEMBAYARAN', 'notes' => 'Simulasi pembayaran 50%; bukan transaksi nyata.',
                    ], $user);
                }
            }
            $this->command?->info('Simulasi siap: '.$plan->name.' | '.$plan->reference);
            $this->command?->info('2 nota siap finalisasi, 1 menunggu nota, 1 cargo berjalan, 1 cargo tiba dan dibayar 50%.');
        });
    }

    /** @return list<array{string, int, string}> */
    private function products(): array
    {
        return [
            ['Ban Tubeless IRC Fasti Pro 90/80-14', 210000, 'pcs'],
            ['Busi NGK CPR9EA-9', 22000, 'pcs'],
            ['Kampas Rem Depan Honda Beat AHM', 45000, 'set'],
            ['Oli Mesin Yamalube Super Matic 1 Liter', 58000, 'botol'],
            ['Oli Gardan Federal Gear Matic 120 ml', 18000, 'botol'],
            ['V-Belt Honda Vario 125 dan 150 Original', 125000, 'pcs'],
            ['Roller CVT Yamaha Mio M3 125 Original', 65000, 'set'],
            ['Filter Udara Honda Scoopy eSP', 48000, 'pcs'],
            ['Rantai dan Gear Set Yamaha Jupiter Z', 185000, 'set'],
            ['Aki Motor GS Astra GTZ5S Maintenance Free', 245000, 'pcs'],
            ['Shockbreaker YSS DTG Honda Vario 150', 425000, 'set'],
            ['Lampu Depan LED H4 Motor 25 Watt', 95000, 'pcs'],
            ['Bearing Roda Depan SKF 6301', 35000, 'pcs'],
            ['Seal Shock Depan Honda Beat dan Scoopy', 32000, 'set'],
            ['Kabel Gas Yamaha NMAX 155 Generasi Pertama', 85000, 'pcs'],
        ];
    }
}
