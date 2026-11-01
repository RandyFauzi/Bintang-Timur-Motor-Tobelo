<?php

namespace App\Services;

use App\Models\Distributor;
use App\Models\DistributorInvoice;
use App\Models\DistributorPayment;
use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DistributorInvoiceService
{
    public static function ensureSchema(): void
    {
        if (cache()->get('distributor_invoices_schema_v2')) {
            return;
        }

        try {
            if (! Schema::hasColumn('distributor_invoices', 'distributor_id')) {
                Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable) {
            try {
                if (! Schema::hasColumn('distributor_invoices', 'distributor_id')) {
                    Schema::table('distributor_invoices', function (Blueprint $table): void {
                        $table->foreignId('distributor_id')->nullable()->after('id')->constrained()->nullOnDelete();
                    });
                }
            } catch (\Throwable) {
                try {
                    if (! Schema::hasColumn('distributor_invoices', 'distributor_id')) {
                        DB::statement('ALTER TABLE distributor_invoices ADD COLUMN distributor_id BIGINT UNSIGNED NULL AFTER id');
                    }
                } catch (\Throwable) {
                    // Ignore
                }
            }

            try {
                DB::statement('ALTER TABLE distributor_invoices MODIFY purchase_order_id BIGINT UNSIGNED NULL');
            } catch (\Throwable) {
                // Ignore
            }
        }

        // Backfill distributor_id from purchase_orders for existing invoices if needed
        try {
            if (Schema::hasColumn('distributor_invoices', 'distributor_id')) {
                $invoices = DB::table('distributor_invoices')
                    ->whereNotNull('purchase_order_id')
                    ->whereNull('distributor_id')
                    ->get(['id', 'purchase_order_id']);

                foreach ($invoices as $inv) {
                    $distributorId = DB::table('purchase_orders')
                        ->where('id', $inv->purchase_order_id)
                        ->value('distributor_id');

                    if ($distributorId) {
                        DB::table('distributor_invoices')
                            ->where('id', $inv->id)
                            ->update(['distributor_id' => $distributorId]);
                    }
                }
            }
        } catch (\Throwable) {
            // Ignore if backfill fails
        }

        if (Schema::hasColumn('distributor_invoices', 'distributor_id')) {
            cache()->forever('distributor_invoices_schema_v2', true);
        }
    }

    public function createForOrder(PurchaseOrder $order, ?string $dueOn = null, int $termMonths = 3): DistributorInvoice
    {
        self::ensureSchema();

        $total = 0;
        foreach ($order->items as $item) {
            $subtotal = self::cents($item->unit_price) * $item->quantity;
            if ($subtotal > 9000000000000000 - $total) {
                throw ValidationException::withMessages(['items' => 'Total pesanan terlalu besar. Pisahkan menjadi beberapa PO.']);
            }
            $total += $subtotal;
        }

        return $order->invoice()->firstOrCreate([], [
            'distributor_id' => $order->distributor_id,
            'reference' => 'INV-'.$order->reference,
            'due_on' => $dueOn ?? CarbonImmutable::parse($order->ordered_on->toDateString())->addMonthsNoOverflow($termMonths)->toDateString(),
            'total_cents' => $total,
        ]);
    }

    /** @param array{distributor_id?:?int,distributor_name?:?string,reference?:?string,total:numeric-string|float|int,due_on:string,invoiced_on?:?string,received_on?:?string,supplier_reference?:?string,shipment_id?:?int,notes?:?string} $data */
    public function createManual(array $data): DistributorInvoice
    {
        self::ensureSchema();

        return DB::transaction(function () use ($data): DistributorInvoice {
            $totalCents = self::cents($data['total']);
            if ($totalCents <= 0 || $totalCents > 9000000000000000) {
                throw ValidationException::withMessages(['total' => 'Total tagihan harus lebih dari 0 dan dalam batas yang valid.']);
            }

            $distributorId = $data['distributor_id'] ?? null;
            if (! empty($data['distributor_name'])) {
                $distributorName = trim((string) $data['distributor_name']);
                $distributor = Distributor::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($distributorName)])->first()
                    ?? Distributor::create(['name' => $distributorName, 'is_active' => true]);
                $distributorId = $distributor->id;
            } elseif ($distributorId) {
                $distributorId = (int) $distributorId;
            } else {
                throw ValidationException::withMessages(['distributor_name' => 'Pilih atau tulis nama distributor terlebih dahulu.']);
            }

            $reference = ! empty($data['reference']) ? trim($data['reference']) : $this->generateManualReference();

            if (DistributorInvoice::where('reference', $reference)->exists()) {
                throw ValidationException::withMessages(['reference' => 'Nomor invoice '.$reference.' sudah digunakan.']);
            }

            $invoicedOn = ! empty($data['invoiced_on']) ? $data['invoiced_on'] : DistributorInvoice::today()->toDateString();
            $dueOn = $data['due_on'];

            if ($invoicedOn && $dueOn < $invoicedOn) {
                throw ValidationException::withMessages(['due_on' => 'Jatuh tempo tidak boleh sebelum tanggal faktur.']);
            }

            return DistributorInvoice::create([
                'distributor_id' => $distributorId,
                'purchase_order_id' => null,
                'reference' => $reference,
                'invoiced_on' => $invoicedOn,
                'received_on' => empty($data['shipment_id']) ? ($data['received_on'] ?? null) : null,
                'due_on' => $dueOn,
                'total_cents' => $totalCents,
                'paid_cents' => 0,
                'version' => 1,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'shipment_id' => $data['shipment_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /** @param list<string> $reservedReferences */
    public function generateManualReference(array $reservedReferences = []): string
    {
        $reservedReferences = array_map('mb_strtolower', $reservedReferences);
        $prefix = 'INV-MAN-'.date('Ym').'-';
        $latest = DistributorInvoice::where('reference', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('reference');

        $counter = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $m)) {
            $counter = ((int) $m[1]) + 1;
        }

        do {
            $ref = $prefix.sprintf('%04d', $counter++);
        } while (in_array(mb_strtolower($ref), $reservedReferences, true) || DistributorInvoice::where('reference', $ref)->exists());

        return $ref;
    }

    public static function cents(string|int|float $amount): int
    {
        $parts = explode('.', (string) $amount, 2);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    /** @param array{request_id:string,version:int,amount:numeric-string,paid_on:string,method:string,reference?:?string,notes?:?string} $data */
    public function pay(DistributorInvoice $invoice, array $data, User $user): void
    {
        DB::transaction(function () use ($invoice, $data, $user): void {
            $invoice = DistributorInvoice::lockForUpdate()->findOrFail($invoice->id);
            $amount = self::cents($data['amount']);
            $existing = DistributorPayment::where('request_id', $data['request_id'])->first();
            if ($existing) {
                if ($existing->distributor_invoice_id !== $invoice->id || $existing->amount_cents !== $amount || $existing->paid_on->toDateString() !== $data['paid_on'] || $existing->method !== $data['method'] || $existing->reference !== ($data['reference'] ?? null) || $existing->notes !== ($data['notes'] ?? null)) {
                    throw ValidationException::withMessages(['request_id' => 'Permintaan pembayaran sudah digunakan. Muat ulang halaman.']);
                }

                return;
            }
            $this->assertVersion($invoice, (int) $data['version']);
            if ($amount > $invoice->remainingCents() || $amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Nominal harus lebih dari 0 dan tidak boleh melebihi sisa tagihan.']);
            }
            $minDate = $invoice->purchaseOrder?->ordered_on?->toDateString() ?? $invoice->invoiced_on?->toDateString();
            if ($minDate && $data['paid_on'] < $minDate) {
                throw ValidationException::withMessages(['paid_on' => 'Tanggal pembayaran tidak boleh sebelum tanggal '.($invoice->purchaseOrder ? 'PO' : 'faktur').'.']);
            }
            $invoice->payments()->create(collect($data)->only(['request_id', 'paid_on', 'method', 'reference', 'notes'])->all() + ['amount_cents' => $amount, 'created_by' => $user->id]);
            $invoice->paid_cents += $amount;
            $invoice->version++;
            $invoice->save();
        });
    }

    /** @param array{version:int, due_on:string, distributor_id?:?int, reference?:?string, total?:numeric-string|float|int, invoiced_on?:?string,received_on?:?string, shipment_id?:?int, supplier_reference?:?string, notes?:?string, discrepancy_notes?:?string, claim_amount?:?string} $data */
    public function updateDetails(DistributorInvoice $invoice, array $data): void
    {
        self::ensureSchema();

        DB::transaction(function () use ($invoice, $data): void {
            $invoice = DistributorInvoice::lockForUpdate()->findOrFail($invoice->id);
            $this->assertVersion($invoice, (int) $data['version']);
            if ($invoice->purchaseOrder?->note_issued) {
                foreach (['supplier_reference' => $invoice->supplier_reference, 'invoiced_on' => $invoice->invoiced_on?->toDateString(), 'shipment_id' => $invoice->shipment_id] as $field => $current) {
                    if (array_key_exists($field, $data) && (string) ($data[$field] ?? '') !== (string) ($current ?? '')) {
                        throw ValidationException::withMessages([$field => 'Nota pengadaan dikelola dari rencana pengadaan. Nomor nota, tanggal, dan cargo tidak dapat diubah dari tagihan.']);
                    }
                }
            }
            if ($invoice->isManual()) {
                if (! empty($data['distributor_name'])) {
                    $distributorName = trim((string) $data['distributor_name']);
                    $distributor = Distributor::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($distributorName)])->first()
                        ?? Distributor::create(['name' => $distributorName, 'is_active' => true]);
                    $invoice->distributor_id = $distributor->id;
                } elseif (array_key_exists('distributor_id', $data) && ! empty($data['distributor_id'])) {
                    $invoice->distributor_id = (int) $data['distributor_id'];
                }
                if (array_key_exists('reference', $data) && ! empty($data['reference'])) {
                    $ref = trim((string) $data['reference']);
                    if (DistributorInvoice::where('reference', $ref)->where('id', '!=', $invoice->id)->exists()) {
                        throw ValidationException::withMessages(['reference' => 'Nomor invoice '.$ref.' sudah digunakan.']);
                    }
                    $invoice->reference = $ref;
                }
                if (array_key_exists('total', $data)) {
                    $newTotal = self::cents($data['total']);
                    if ($newTotal < $invoice->paid_cents) {
                        throw ValidationException::withMessages(['total' => 'Total tagihan tidak boleh lebih kecil dari jumlah yang sudah dibayar (Rp '.number_format($invoice->paid_cents / 100, 2, ',', '.').').']);
                    }
                    $invoice->total_cents = $newTotal;
                }
            }
            $shipmentId = array_key_exists('shipment_id', $data) ? $data['shipment_id'] : $invoice->shipment_id;
            if (! empty($data['received_on']) && (! $invoice->isManual() || $shipmentId !== null)) {
                throw ValidationException::withMessages(['received_on' => 'Tanggal masuk barang mengikuti penerimaan pengiriman.']);
            }
            if ($invoice->isManual()) {
                if ($shipmentId !== null) {
                    $invoice->received_on = null;
                } elseif (array_key_exists('received_on', $data)) {
                    $invoice->received_on = $data['received_on'];
                }
            }
            $invoice->fill(collect($data)->only(['due_on', 'invoiced_on', 'shipment_id', 'supplier_reference', 'notes', 'discrepancy_notes'])->all());
            if (array_key_exists('claim_amount', $data)) {
                $invoice->claim_amount_cents = self::cents($data['claim_amount'] ?? '0');
            }
            if ($invoice->invoiced_on && $invoice->due_on->toDateString() < $invoice->invoiced_on->toDateString()) {
                throw ValidationException::withMessages(['due_on' => 'Jatuh tempo tidak boleh sebelum tanggal faktur.']);
            }
            if ($invoice->claim_amount_cents > $invoice->total_cents) {
                throw ValidationException::withMessages(['claim_amount' => 'Nilai klaim tidak boleh melebihi total faktur.']);
            }
            if ($invoice->claim_amount_cents > 0 && trim($invoice->discrepancy_notes ?? '') === '') {
                throw ValidationException::withMessages(['discrepancy_notes' => 'Isi keterangan retur atau selisih untuk nilai klaim ini.']);
            }
            $invoice->version++;
            $invoice->save();
        });
    }

    /** @param list<array{id:int, version:int}> $selected */
    public function deleteMany(array $selected): void
    {
        DB::transaction(function () use ($selected): void {
            $versions = collect($selected)->pluck('version', 'id');
            $invoices = DistributorInvoice::whereKey($versions->keys())->orderBy('id')->lockForUpdate()->get();
            if ($invoices->count() !== count($selected)) {
                throw ValidationException::withMessages(['selected' => 'Sebagian invoice tidak tersedia. Muat ulang halaman dan periksa kembali pilihan Anda.']);
            }
            foreach ($invoices as $invoice) {
                $this->assertVersion($invoice, (int) $versions[$invoice->id]);
            }
            DistributorPayment::whereIn('distributor_invoice_id', $invoices->modelKeys())->delete();
            foreach ($invoices as $invoice) {
                $invoice->delete();
            }
        });
    }

    public function delete(DistributorInvoice $invoice, int $version): void
    {
        self::ensureSchema();

        DB::transaction(function () use ($invoice, $version): void {
            $invoice = DistributorInvoice::lockForUpdate()->findOrFail($invoice->id);
            $this->assertVersion($invoice, $version);
            $invoice->payments()->delete();
            $invoice->delete();
        });
    }

    public function assertVersion(DistributorInvoice $invoice, int $version): void
    {
        if ($invoice->version !== $version) {
            throw ValidationException::withMessages(['version' => 'Tagihan sudah berubah. Muat ulang halaman sebelum menyimpan.']);
        }
    }

    /** @return array{count:int,alerts:list<array{message:string,url:string}>} */
    public function notifications(): array
    {
        $query = DistributorInvoice::needsReminder();

        return ['count' => (clone $query)->count(), 'alerts' => $query->with(['purchaseOrder', 'distributor'])->orderBy('due_on')->orderBy('id')->limit(5)->get()->map(fn (DistributorInvoice $invoice): array => ['message' => $invoice->distributor_name.' · '.$invoice->timingLabel(), 'url' => route('distributor-invoices.show', $invoice)])->all()];
    }
}
