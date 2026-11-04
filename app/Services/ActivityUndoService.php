<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\BusinessSetting;
use App\Models\Color;
use App\Models\CompetitorObservation;
use App\Models\Distributor;
use App\Models\DistributorInvoice;
use App\Models\DistributorPayment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ProductReturnEvent;
use App\Models\ProductStock;
use App\Models\ProductType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePlan;
use App\Models\Sale;
use App\Models\SaleCorrection;
use App\Models\SaleItem;
use App\Models\Shipment;
use App\Models\ShippingService;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ActivityUndoService
{
    /** @var array<string, class-string<Model>> */
    private const ENTITY_CLASS_MAP = [
        'Product' => Product::class,
        'ProductStock' => ProductStock::class,
        'StockMovement' => StockMovement::class,
        'Distributor' => Distributor::class,
        'DistributorInvoice' => DistributorInvoice::class,
        'DistributorPayment' => DistributorPayment::class,
        'PurchaseOrder' => PurchaseOrder::class,
        'PurchaseOrderItem' => PurchaseOrderItem::class,
        'PurchasePlan' => PurchasePlan::class,
        'Shipment' => Shipment::class,
        'ShippingService' => ShippingService::class,
        'Expense' => Expense::class,
        'Sale' => Sale::class,
        'SaleItem' => SaleItem::class,
        'SaleCorrection' => SaleCorrection::class,
        'ProductReturn' => ProductReturn::class,
        'ProductReturnEvent' => ProductReturnEvent::class,
        'CompetitorObservation' => CompetitorObservation::class,
        'Workshop' => Workshop::class,
        'WorkType' => WorkType::class,
        'Brand' => Brand::class,
        'ProductType' => ProductType::class,
        'Color' => Color::class,
        'User' => User::class,
        'BusinessSetting' => BusinessSetting::class,
    ];

    /**
     * Undo the changes recorded in an ActivityLog.
     *
     * @throws ValidationException
     */
    public function undo(ActivityLog $log, User $actor): void
    {
        if ($log->isUndone()) {
            throw ValidationException::withMessages([
                'activity' => 'Aktivitas ini sudah pernah dikembalikan (di-undo) pada '.$log->undone_at?->format('d/m/Y H:i').' oleh '.($log->undone_by_name ?? 'Sistem').'.',
            ]);
        }

        if (! $log->canBeUndone()) {
            throw ValidationException::withMessages([
                'activity' => 'Aktivitas ini tidak dapat dikembalikan (tidak ada perubahan data atau log telah diringkas).',
            ]);
        }

        $changes = (array) ($log->getAttribute('changes') ?? []);
        if (empty($changes)) {
            throw ValidationException::withMessages([
                'activity' => 'Tidak ada rekaman perubahan data pada aktivitas ini yang dapat dikembalikan.',
            ]);
        }

        DB::transaction(function () use ($log, $actor, $changes): void {
            // Reverse changes in stack order (last change undone first)
            $reversed = array_reverse($changes);

            foreach ($reversed as $change) {
                $this->revertChange($change);
            }

            // Mark the activity log as undone
            $log->markAsUndone(null, $actor);
        });
    }

    /**
     * @param  array{entity: string, id: string|int, event: string, before?: array<string, mixed>, after?: array<string, mixed>}  $change
     */
    private function revertChange(array $change): void
    {
        $rawEntity = (string) ($change['entity'] ?? '');
        $id = $change['id'] ?? null;
        $event = (string) ($change['event'] ?? '');
        $before = (array) ($change['before'] ?? []);

        $modelClass = self::ENTITY_CLASS_MAP[$rawEntity] ?? ('App\\Models\\'.$rawEntity);
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return;
        }

        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);

        match ($event) {
            'created' => $this->revertCreated($modelClass, $id, $usesSoftDeletes),
            'updated' => $this->revertUpdated($modelClass, $id, $before, $usesSoftDeletes),
            'deleted' => $this->revertDeleted($modelClass, $id, $before, $usesSoftDeletes),
            default => null,
        };
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function revertCreated(string $modelClass, mixed $id, bool $usesSoftDeletes): void
    {
        if ($id === null) {
            return;
        }

        $query = $usesSoftDeletes ? $modelClass::withTrashed() : $modelClass::query();
        $model = $query->find($id);

        if ($model) {
            $model->delete();
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $before
     */
    private function revertUpdated(string $modelClass, mixed $id, array $before, bool $usesSoftDeletes): void
    {
        if ($id === null || empty($before)) {
            return;
        }

        $query = $usesSoftDeletes ? $modelClass::withTrashed() : $modelClass::query();
        $model = $query->find($id);

        if (! $model) {
            return;
        }

        $attributes = $this->cleanAttributes($model, $before);
        if (! empty($attributes)) {
            $model->forceFill($attributes)->save();
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $before
     */
    private function revertDeleted(string $modelClass, mixed $id, array $before, bool $usesSoftDeletes): void
    {
        if ($id === null) {
            return;
        }

        if ($usesSoftDeletes) {
            $model = $modelClass::withTrashed()->find($id);
            if ($model) {
                if ($model->trashed()) {
                    $model->restore();
                }
                $attributes = $this->cleanAttributes($model, $before);
                if (! empty($attributes)) {
                    $model->forceFill($attributes)->save();
                }

                return;
            }
        }

        // Hard-deleted: check if record already exists
        $model = (new $modelClass)->find($id);
        if (! $model) {
            $attributes = $this->cleanAttributes(new $modelClass, $before);
            $attributes['id'] = $id;

            $modelClass::unguarded(function () use ($modelClass, $attributes): void {
                $modelClass::create($attributes);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function cleanAttributes(Model $model, array $attributes): array
    {
        $table = $model->getTable();
        $cleaned = [];

        foreach ($attributes as $key => $value) {
            if ($value === '[DISAMARKAN]' || $value === '[RINGKASAN]') {
                continue;
            }

            if (in_array($key, ['created_at', 'updated_at', 'deleted_at', 'remember_token'], true)) {
                continue;
            }

            if (Schema::hasColumn($table, $key)) {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }
}
