<?php

namespace App\Console\Commands;

use App\Services\SupplierProductService;
use Database\Seeders\PurchasePlanDemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Signature('demo:purchase-plan {--reset : Hapus simulasi lama dan buat ulang dengan ID produk baru}')]
#[Description('Buat simulasi PO 5 distributor dan 15 jenis barang tanpa mengubah data operasional')]
class ResetPurchasePlanDemo extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PurchasePlanDemoSeeder $demo, SupplierProductService $supplierProducts): int
    {
        try {
            $supplierProducts->ensureSchemaReady();
            $demo->setContainer($this->laravel)->setCommand($this);
            DB::transaction(function () use ($demo): void {
                if ($this->option('reset')) {
                    $demo->reset();
                }
                $demo->__invoke();
            });
        } catch (ValidationException $exception) {
            $this->error(implode(' ', $exception->validator->errors()->all()));

            return self::FAILURE;
        }

        $this->info('Simulasi siap. Setiap produk memiliki satu distributor, ID dan modal tersendiri.');

        return self::SUCCESS;
    }
}
