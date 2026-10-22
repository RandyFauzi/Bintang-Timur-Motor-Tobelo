<?php

namespace Tests\Feature;

use App\Models\DistributorInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchasePlan;
use App\Models\ShippingService;
use App\Models\User;
use Database\Seeders\PurchasePlanDemoSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PurchaseOrderArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_archiving_a_processed_plan_preserves_bills_payments_and_shipping_and_can_be_restored(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->seed(PurchasePlanDemoSeeder::class);
        $plan = PurchasePlan::sole();
        $order = $plan->orders()->oldest('id')->firstOrFail();
        $invoiceIds = DistributorInvoice::orderBy('id')->pluck('id')->all();

        $this->delete(route('purchase-orders.destroy', $order), ['version' => $order->version])
            ->assertRedirect(route('purchase-orders.index'))->assertSessionHasNoErrors();

        $this->assertSame(5, PurchaseOrder::whereNotNull('archived_at')->count());
        $this->assertDatabaseCount('purchase_order_items', 75);
        $this->assertDatabaseCount('shipments', 2);
        $this->assertDatabaseCount('distributor_payments', 1);
        $this->assertSame($invoiceIds, DistributorInvoice::orderBy('id')->pluck('id')->all());
        $this->assertSame(0, $this->get(route('purchase-orders.index'))->assertOk()->viewData('orders')->total());
        $this->assertSame(1, $this->get(route('purchase-orders.index', ['archived' => 1]))->assertOk()->assertSee('Kembalikan ke Aktif')->viewData('orders')->total());
        $this->get(route('purchase-plans.show', $plan))->assertOk()->assertSee('Pengadaan ini ada di Arsip');
        foreach (DistributorInvoice::with('shipment')->get() as $invoice) {
            $this->get(route('distributor-invoices.show', $invoice))->assertOk();
            if ($invoice->shipment_id) {
                $this->get(route('shipments.index', ['shipment' => $invoice->shipment_id]))->assertOk();
            }
        }

        $order->refresh();
        $this->postJson(route('purchase-plans.orders.finalize', [$plan, $order]), [
            'version' => $order->version, 'shipping_service_id' => ShippingService::where('is_active', true)->firstOrFail()->id,
            'origin_port' => 'Surabaya', 'departed_on' => now()->toDateString(),
        ])->assertOk();
        $invoice = $order->fresh()->invoice;
        $this->post(route('distributor-invoices.pay', $invoice), [
            'request_id' => (string) Str::uuid(), 'version' => $invoice->version, 'amount' => '100.00', 'paid_on' => now()->toDateString(), 'method' => 'transfer',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shipments', 3);
        $this->assertSame(10000, $invoice->refresh()->paid_cents);

        $this->patch(route('purchase-orders.restore', $order), ['version' => $order->fresh()->version])
            ->assertRedirect(route('purchase-orders.index'))->assertSessionHasNoErrors();

        $this->assertSame(0, PurchaseOrder::whereNotNull('archived_at')->count());
        $this->assertSame(1, $this->get(route('purchase-orders.index'))->assertOk()->viewData('orders')->total());
        $this->assertSame(0, $this->get(route('purchase-orders.index', ['archived' => 1]))->assertOk()->viewData('orders')->total());
        $this->assertDatabaseCount('distributor_payments', 2);
        $this->assertSame($invoiceIds, DistributorInvoice::orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('stock_movements', 0);
    }

    #[TestWith([['supplier_reference' => 'NOTA-LEGACY-1']])]
    #[TestWith([['invoiced_on' => '2026-10-21']])]
    #[TestWith([['discrepancy_notes' => 'Barang rusak sedang dikonfirmasi']])]
    #[TestWith([['notes' => 'Catatan tagihan yang perlu dipertahankan']])]
    #[TestWith([['version' => 2]])]
    public function test_unpaid_legacy_invoices_with_document_details_are_preserved(array $details): void
    {
        $invoice = DistributorInvoice::factory()->create($details);
        $before = $invoice->refresh()->getAttributes();
        $order = $invoice->purchaseOrder;

        $this->actingAs(User::factory()->create())->delete(route('purchase-orders.destroy', $order), ['version' => $order->version])
            ->assertRedirect(route('purchase-orders.index'))->assertSessionHasNoErrors();

        $this->assertNotNull($order->refresh()->archived_at);
        $this->assertModelExists($invoice);
        $this->assertSame($before, $invoice->refresh()->getAttributes());
    }

    public function test_pending_archive_migration_keeps_listing_available_and_preserves_processed_orders(): void
    {
        $order = PurchaseOrder::factory()->create();
        $order->note_issued = true;
        $order->save();
        $order->refresh();
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
        $this->actingAs(User::factory()->create());

        $this->assertSame(1, $this->get(route('purchase-orders.index'))->assertOk()->viewData('orders')->total());
        $this->assertSame(0, $this->get(route('purchase-orders.index', ['archived' => 1]))->assertOk()->viewData('orders')->total());
        $this->deleteJson(route('purchase-orders.destroy', $order), ['version' => $order->version])
            ->assertUnprocessable()->assertJsonValidationErrors('purchase_order');
        $this->patchJson(route('purchase-orders.restore', $order), ['version' => $order->version])
            ->assertUnprocessable()->assertJsonValidationErrors('purchase_order');

        $this->assertModelExists($order);
    }

    public function test_guests_cannot_restore_and_stale_versions_cannot_restore_newer_archives(): void
    {
        $order = PurchaseOrder::factory()->create();
        $order->archived_at = now();
        $order->version = 2;
        $order->save();
        $this->patch(route('purchase-orders.restore', $order), ['version' => 2])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->patchJson(route('purchase-orders.restore', $order), ['version' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('version');

        $this->assertNotNull($order->refresh()->archived_at);
        $this->assertSame(2, $order->version);
    }
}
