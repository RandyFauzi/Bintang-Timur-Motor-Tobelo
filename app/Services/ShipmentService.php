<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShippingService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    /** @param array<string, mixed> $data */
    public function save(array $data, User $user, ?Shipment $shipment = null): Shipment
    {
        return DB::transaction(function () use ($data, $user, $shipment): Shipment {
            if ($shipment) {
                $shipment = Shipment::lockForUpdate()->findOrFail($shipment->id);
                $this->assertEditable($shipment, (int) $data['version']);
                $latestNoteDate = $shipment->distributorInvoices()
                    ->whereHas('purchaseOrder', fn (Builder $query) => $query->where('note_issued', true))
                    ->max('invoiced_on');
                $latestNoteDate = $latestNoteDate ? CarbonImmutable::parse($latestNoteDate)->toDateString() : null;
                if ($latestNoteDate && $data['departed_on'] < $latestNoteDate) {
                    throw ValidationException::withMessages(['departed_on' => 'Tanggal keberangkatan tidak boleh sebelum tanggal nota distributor ('.$latestNoteDate.').']);
                }
            }
            $carrier = ShippingService::where(fn (Builder $query) => $query->where('is_active', true)->orWhereKey($shipment?->shipping_service_id ?? 0))->lockForUpdate()->find($data['shipping_service_id']);
            if (! $carrier) {
                throw ValidationException::withMessages(['shipping_service_id' => 'Jasa pengiriman tidak aktif. Pilih jasa lain.']);
            }
            $sameService = $shipment && $shipment->shipping_service_id === $carrier->id;
            $duration = $sameService ? $shipment->duration_days : $carrier->duration_days;
            $data['service_name'] = $sameService ? $shipment->service_name : $carrier->name;
            $data['duration_days'] = $duration;
            $data['estimated_arrival_on'] = CarbonImmutable::parse($data['departed_on'], config('shipping.timezone'))->addDays($duration)->toDateString();
            $data['reference'] = ($data['reference'] ?? null) ?: ($shipment?->reference ?? 'CGO-'.Str::ulid());
            if (array_key_exists('shipping_cost', $data)) {
                $data['shipping_cost_cents'] = $data['shipping_cost'] !== null && $data['shipping_cost'] !== ''
                    ? DistributorInvoiceService::cents($data['shipping_cost'])
                    : 0;
                unset($data['shipping_cost']);
            }
            if (! $shipment) {
                $shipment = new Shipment;
                $shipment->created_by = $user->id;
            } else {
                $shipment->version++;
            }
            $shipment->fill($data)->save();

            return $shipment;
        });
    }

    public function arrive(Shipment $shipment, User $user, int $version): void
    {
        DB::transaction(function () use ($shipment, $user, $version): void {
            $shipment = Shipment::lockForUpdate()->findOrFail($shipment->id);
            if ($shipment->delivered_at) {
                return;
            }
            $this->assertEditable($shipment, $version);
            if ($shipment->departed_on->toDateString() > Shipment::today()->toDateString()) {
                throw ValidationException::withMessages(['shipment' => 'Pengiriman belum berangkat. Periksa tanggal keberangkatan.']);
            }
            $shipment->delivered_at = now();
            $shipment->received_by = $user->id;
            $shipment->version++;
            $shipment->save();
        });
    }

    public function delete(Shipment $shipment, ?int $version = null): void
    {
        DB::transaction(function () use ($shipment, $version): void {
            $shipment = Shipment::lockForUpdate()->findOrFail($shipment->id);
            if ($version !== null && $shipment->version !== $version) {
                throw ValidationException::withMessages(['shipment' => 'Data pengiriman sudah berubah. Muat ulang halaman sebelum melanjutkan.']);
            }
            $shipment->distributorInvoices()->update(['shipment_id' => null]);
            $shipment->delete();
        });
    }

    private function assertEditable(Shipment $shipment, int $version): void
    {
        if ($shipment->delivered_at || $shipment->version !== $version) {
            throw ValidationException::withMessages(['shipment' => 'Data pengiriman sudah berubah atau selesai. Muat ulang halaman.']);
        }
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $counts = [];
        foreach (array_keys(Shipment::STATUSES) as $status) {
            $counts[$status] = Shipment::withStatus($status)->count();
        }

        return $counts;
    }

    /** @return array{count: int, alerts: list<array{message: string, url: string}>} */
    public function notifications(): array
    {
        $query = Shipment::whereNull('delivered_at')->whereDate('departed_on', '<=', Shipment::today()->toDateString())
            ->whereDate('estimated_arrival_on', '<=', Shipment::today()->addDays(config('shipping.near_arrival_days'))->toDateString());
        $count = (clone $query)->count();
        $alerts = $query->orderBy('estimated_arrival_on')->limit(5)->get()->map(fn (Shipment $shipment): array => [
            'message' => $shipment->cargo_name.' · '.Shipment::STATUSES[$shipment->status()].'. Konfirmasi jika sudah diterima.',
            'url' => route('shipments.index', ['shipment' => $shipment->id]),
        ])->all();

        return ['count' => $count, 'alerts' => $alerts];
    }
}
