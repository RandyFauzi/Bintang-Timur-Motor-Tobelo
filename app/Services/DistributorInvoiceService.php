<?php

namespace App\Services;

use App\Models\DistributorInvoice;
use App\Models\DistributorPayment;
use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributorInvoiceService
{
    public function createForOrder(PurchaseOrder $order, ?string $dueOn = null, int $termMonths = 3): DistributorInvoice
    {
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

    /** @param array{distributor_id:int,reference?:?string,total:numeric-string|float|int,due_on:string,invoiced_on?:?string,supplier_reference?:?string,shipment_id?:?int,notes?:?string} $data */
    public function createManual(array $data): DistributorInvoice
    {
        return DB::transaction(function () use ($data): DistributorInvoice {
            $totalCents = self::cents($data['total']);
            if ($totalCents <= 0 || $totalCents > 9000000000000000) {
                throw ValidationException::withMessages(['total' => 'Total tagihan harus lebih dari 0 dan dalam batas yang valid.']);
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
                'distributor_id' => $data['distributor_id'],
                'purchase_order_id' => null,
                'reference' => $reference,
                'invoiced_on' => $invoicedOn,
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

    public function generateManualReference(): string
    {
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
        } while (DistributorInvoice::where('reference', $ref)->exists());

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

    /** @param array{version:int, due_on:string, total?:numeric-string|float|int, invoiced_on?:?string, shipment_id?:?int, supplier_reference?:?string, notes?:?string, discrepancy_notes?:?string, claim_amount?:?string} $data */
    public function updateDetails(DistributorInvoice $invoice, array $data): void
    {
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
            if ($invoice->isManual() && array_key_exists('total', $data)) {
                $newTotal = self::cents($data['total']);
                if ($newTotal < $invoice->paid_cents) {
                    throw ValidationException::withMessages(['total' => 'Total tagihan tidak boleh lebih kecil dari jumlah yang sudah dibayar.']);
                }
                $invoice->total_cents = $newTotal;
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

    public function delete(DistributorInvoice $invoice, ?int $version = null): void
    {
        DB::transaction(function () use ($invoice, $version): void {
            $invoice = DistributorInvoice::lockForUpdate()->findOrFail($invoice->id);
            if ($version !== null) {
                $this->assertVersion($invoice, $version);
            }
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
