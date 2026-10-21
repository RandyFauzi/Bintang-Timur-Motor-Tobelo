<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\DistributorInvoice;
use App\Models\DistributorPayment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePlan;
use App\Models\ShippingService;
use App\Models\User;
use App\Services\ShipmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PurchaseOrderNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_and_finalize_endpoints_require_authentication_and_matching_plan(): void
    {
        [$plan, $order] = $this->order();
        $other = PurchasePlan::factory()->create(['status' => 'finalized']);
        $this->patch($this->noteUrl($plan, $order))->assertRedirect(route('login'));
        $this->post($this->finalizeUrl($plan, $order))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());

        $this->patchJson($this->noteUrl($other, $order), $this->notePayload($order))->assertNotFound();
        $this->postJson($this->finalizeUrl($other, $order), [])->assertNotFound();

        $this->assertDatabaseCount('distributor_invoices', 0);
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_saved_invoice_quantities_and_bill_do_not_change_order_or_received_stock(): void
    {
        [$plan, $order, $item] = $this->order();
        $this->actingAs(User::factory()->create());

        $this->patchJson($this->noteUrl($plan, $order), $this->notePayload($order, 5))
            ->assertOk()->assertJsonPath('order.note_issued', true)->assertJsonPath('order.items.0.invoice_quantity', 5);

        $this->assertSame(15, $item->refresh()->quantity);
        $this->assertNull($item->actual_quantity);
        $this->assertSame(5, $item->invoice_quantity);
        $invoice = DistributorInvoice::sole();
        $this->assertSame(6000000, $invoice->total_cents);
        $this->assertSame('INV-WMS-01', $invoice->supplier_reference);
        $this->assertSame('2026-10-21', $invoice->invoiced_on->toDateString());
        $this->assertSame('2027-01-21', $invoice->due_on->toDateString());
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('product_stocks', 0);
        $this->get(route('purchase-plans.show', $plan))->assertOk()->assertSee('QTY INVOICE')->assertSee('Perlu Tindakan')->assertSee('Finalisasi Pengiriman');
        $this->get(route('distributor-invoices.show', $invoice))->assertOk()->assertSee('60.000,00');
    }

    public function test_note_can_be_corrected_and_reverted_before_payment_or_shipping(): void
    {
        [$plan, $order, $item] = $this->order();
        $this->actingAs(User::factory()->create());
        $this->patchJson($this->noteUrl($plan, $order), $this->notePayload($order, 5))->assertOk();
        $this->patchJson($this->noteUrl($plan, $order), $this->notePayload($order->fresh(), 8))->assertOk();
        $this->assertDatabaseCount('distributor_invoices', 1);
        $this->assertSame(9600000, DistributorInvoice::sole()->total_cents);
        $payload = $this->notePayload($order->fresh());
        $payload['note_issued'] = false;

        $this->patchJson($this->noteUrl($plan, $order), $payload)->assertOk()->assertJsonPath('order.note_issued', false);

        $this->assertNull($item->fresh()->invoice_quantity);
        $this->assertNull($order->fresh()->supplier_note_number);
        $this->assertDatabaseCount('distributor_invoices', 0);
    }

    public function test_issued_note_requires_number_date_and_all_valid_item_quantities(): void
    {
        [$plan, $order, $item] = $this->order();
        $foreign = PurchaseOrderItem::factory()->create();
        $this->actingAs(User::factory()->create());
        $payload = ['version' => 1, 'note_issued' => true, 'items' => [['id' => $item->id, 'invoice_quantity' => null]]];

        $this->patchJson($this->noteUrl($plan, $order), $payload)->assertUnprocessable()->assertJsonValidationErrors(['supplier_note_number', 'supplier_note_on', 'items.0.invoice_quantity']);
        $payload = $this->notePayload($order);
        $payload['items'][0]['id'] = $foreign->id;
        $this->patchJson($this->noteUrl($plan, $order), $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.id');
        $payload['items'] = [];
        $this->patchJson($this->noteUrl($plan, $order), $payload)->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('distributor_invoices', 0);
        $this->assertNull($foreign->fresh()->invoice_quantity);
    }

    #[TestWith([-1])]
    #[TestWith([1.5])]
    #[TestWith([1000001])]
    public function test_invalid_invoice_quantities_are_rejected(int|float $quantity): void
    {
        [$plan, $order] = $this->order();
        $payload = $this->notePayload($order);
        $payload['items'][0]['invoice_quantity'] = $quantity;

        $this->actingAs(User::factory()->create())->patchJson($this->noteUrl($plan, $order), $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.invoice_quantity');

        $this->assertDatabaseCount('distributor_invoices', 0);
    }

    public function test_stale_notes_and_draft_plans_cannot_be_saved(): void
    {
        [$plan, $order] = $this->order();
        $this->actingAs(User::factory()->create());
        $this->patchJson($this->noteUrl($plan, $order), $this->notePayload($order))->assertOk();
        $this->patchJson($this->noteUrl($plan, $order), $this->notePayload($order))->assertUnprocessable()->assertJsonValidationErrors('version');
        $plan->update(['status' => 'draft']);

        $this->patchJson($this->noteUrl($plan, $order), $this->notePayload($order->fresh()))->assertUnprocessable()->assertJsonValidationErrors('note');
    }

    public function test_finalization_automatically_creates_separate_cargo_for_each_distributor_and_is_idempotent(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 03:00:00', 'UTC'));
        [$plan, $first] = $this->order();
        [$unused, $second] = $this->order($plan, 'WIN');
        $carrier = ShippingService::factory()->create(['duration_days' => 7]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->patchJson($this->noteUrl($plan, $first), $this->notePayload($first, 5))->assertOk();
        $this->patchJson($this->noteUrl($plan, $second), $this->notePayload($second, 2))->assertOk();
        $firstPayload = $this->shippingPayload($first->fresh(), $carrier, 'Surabaya');
        $secondPayload = $this->shippingPayload($second->fresh(), $carrier, 'Makassar');

        $this->postJson($this->finalizeUrl($plan, $first), $firstPayload)->assertOk()->assertJsonPath('order.finalized', true);
        $this->postJson($this->finalizeUrl($plan, $second), $secondPayload)->assertOk();
        $this->postJson($this->finalizeUrl($plan, $first), $firstPayload)->assertOk();

        $this->assertDatabaseCount('shipments', 2);
        $this->assertDatabaseCount('distributor_invoices', 2);
        $firstInvoice = $first->fresh()->invoice;
        $secondInvoice = $second->fresh()->invoice;
        $this->assertNotSame($firstInvoice->shipment_id, $secondInvoice->shipment_id);
        $this->assertSame('Surabaya', $firstInvoice->shipment->origin_port);
        $this->assertSame('Makassar', $secondInvoice->shipment->origin_port);
        $this->assertSame('2026-10-28', $firstInvoice->shipment->estimated_arrival_on->toDateString());
        $this->assertSame('Tobelo', $firstInvoice->shipment->destination_port);
        $this->assertNotNull($first->fresh()->shipment_finalized_at);
        $this->patchJson($this->noteUrl($plan, $first), $this->notePayload($first->fresh(), 8))->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->get(route('shipments.index', ['shipment' => $firstInvoice->shipment_id]))->assertOk()->assertSee($firstInvoice->reference);
        app(ShipmentService::class)->arrive($firstInvoice->shipment, $user, 1);
        $this->assertNotNull($firstInvoice->fresh()->receivedAt());
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_finalization_rejects_unissued_empty_stale_and_inactive_carrier_requests(): void
    {
        [$plan, $order] = $this->order();
        $carrier = ShippingService::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->postJson($this->finalizeUrl($plan, $order), $this->shippingPayload($order, $carrier))->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->patchJson($this->noteUrl($plan, $order), $this->notePayload($order, 0))->assertOk();
        $this->postJson($this->finalizeUrl($plan, $order), $this->shippingPayload($order->fresh(), $carrier))->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->patchJson($this->noteUrl($plan, $order), $this->notePayload($order->fresh(), 5))->assertOk();
        $this->postJson($this->finalizeUrl($plan, $order), $this->shippingPayload($order, $carrier))->assertUnprocessable()->assertJsonValidationErrors('version');
        $carrier->update(['is_active' => false]);
        $this->postJson($this->finalizeUrl($plan, $order), $this->shippingPayload($order->fresh(), $carrier))->assertUnprocessable()->assertJsonValidationErrors('shipping_service_id');

        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_paid_notes_cannot_be_rewritten_and_legacy_invoice_editor_cannot_bypass_note_lock(): void
    {
        [$plan, $order] = $this->order();
        $this->actingAs(User::factory()->create());
        $this->patchJson($this->noteUrl($plan, $order), $this->notePayload($order, 5))->assertOk();
        $invoice = DistributorInvoice::sole();
        DistributorPayment::factory()->create(['distributor_invoice_id' => $invoice->id]);

        $this->patchJson($this->noteUrl($plan, $order), $this->notePayload($order->fresh(), 8))->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->patchJson(route('distributor-invoices.update', $invoice), ['version' => 1, 'due_on' => '2027-01-21', 'supplier_reference' => 'CHANGED'])->assertUnprocessable()->assertJsonValidationErrors('supplier_reference');
        $this->deleteJson(route('purchase-orders.destroy', $order), ['version' => $order->fresh()->version])->assertUnprocessable();

        $this->assertSame('INV-WMS-01', $invoice->fresh()->supplier_reference);
        $this->assertSame(6000000, $invoice->total_cents);
    }

    public function test_shortages_are_aggregated_and_selected_products_prefill_a_new_plan(): void
    {
        [$plan, $first] = $this->order();
        [$unused, $second] = $this->order($plan, 'WIN');
        $this->actingAs(User::factory()->create());
        $this->patchJson($this->noteUrl($plan, $first), $this->notePayload($first, 5))->assertOk();
        $this->patchJson($this->noteUrl($plan, $second), $this->notePayload($second, 2))->assertOk();

        $this->get(route('purchase-orders.create', ['replan' => $plan->id, 'source_items' => [0]]))->assertOk()
            ->assertViewHas('initial', fn (array $initial): bool => $initial['items'][0]['quantity'] === 23 && $initial['items'][0]['product_name'] === 'Yamalube 02 Oil' && $initial['items'][0]['color'] === 'Gold' && $initial['items'][0]['allocations'] === []);

        $this->assertDatabaseCount('purchase_plans', 1);
        $prefilled = $this->get(route('purchase-orders.create', ['replan' => $plan->id, 'source_items' => [0]]))->viewData('initial');
        $prefilled['intent'] = 'draft';
        $this->postJson(route('purchase-plans.store'), $prefilled)->assertOk();
        $this->assertDatabaseCount('purchase_plans', 2);
        $this->assertDatabaseHas('purchase_plans', ['request_id' => $prefilled['request_id'], 'status' => 'draft']);
        $this->patchJson($this->noteUrl($plan, $first), $this->notePayload($first->fresh(), 20))->assertOk();
        $this->get(route('purchase-orders.create', ['replan' => $plan->id, 'source_items' => [0]]))->assertOk()->assertViewHas('initial', fn (array $initial): bool => $initial['items'][0]['quantity'] === 8);
        $this->get(route('purchase-orders.create', ['replan' => $plan->id, 'source_items' => [1]]))->assertUnprocessable();
    }

    public function test_claimed_invoice_is_preserved_when_note_reversal_is_attempted(): void
    {
        [$plan, $order] = $this->order();
        $this->actingAs(User::factory()->create());
        $this->patchJson($this->noteUrl($plan, $order), $this->notePayload($order, 5))->assertOk();
        $invoice = DistributorInvoice::sole();
        $invoice->update(['claim_amount_cents' => 10000, 'discrepancy_notes' => 'Klaim kerusakan kemasan']);
        $payload = $this->notePayload($order->fresh());
        $payload['note_issued'] = false;

        $this->patchJson($this->noteUrl($plan, $order), $payload)->assertUnprocessable()->assertJsonValidationErrors('note');

        $this->assertModelExists($invoice);
        $this->assertSame(10000, $invoice->fresh()->claim_amount_cents);
        $this->assertTrue($order->fresh()->note_issued);
    }

    public function test_entered_total_is_authoritative_for_zero_price_products_and_survives_shipping_finalization(): void
    {
        [$plan, $order, $item] = $this->order();
        $item->update(['unit_price' => 0]);
        $payload = $this->notePayload($order, 5);
        $payload['supplier_note_total'] = '62500.75';
        $this->actingAs(User::factory()->create());

        $this->patchJson($this->noteUrl($plan, $order), $payload)->assertOk()->assertJsonPath('order.supplier_note_total', '62500.75');

        $this->assertSame(6250075, $order->fresh()->supplier_note_total_cents);
        $invoice = DistributorInvoice::sole();
        $this->assertSame(6250075, $invoice->total_cents);
        $this->get(route('distributor-invoices.show', $invoice))->assertOk()->assertSee('62.500,75')->assertSee('Total tagihan mengikuti nominal nota yang divalidasi.');
        $payload['version'] = $order->fresh()->version;
        $payload['items'][0]['invoice_quantity'] = 8;
        $this->patchJson($this->noteUrl($plan, $order), $payload)->assertOk();
        $this->assertSame(6250075, $invoice->fresh()->total_cents);
        $carrier = ShippingService::factory()->create();
        $this->postJson($this->finalizeUrl($plan, $order), $this->shippingPayload($order->fresh(), $carrier))->assertOk();
        $this->assertSame(6250075, $invoice->fresh()->total_cents);
        $this->assertDatabaseCount('distributor_invoices', 1);
        $this->assertDatabaseCount('shipments', 1);
    }

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['-1'])]
    #[TestWith(['1.001'])]
    #[TestWith(['1e4'])]
    #[TestWith(['90000000000001'])]
    public function test_issued_note_rejects_missing_or_invalid_total_without_creating_debt(?string $amount): void
    {
        [$plan, $order, $item] = $this->order();
        $payload = $this->notePayload($order);
        $payload['supplier_note_total'] = $amount;

        $this->actingAs(User::factory()->create())->patchJson($this->noteUrl($plan, $order), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('supplier_note_total');

        $this->assertDatabaseCount('distributor_invoices', 0);
        $this->assertNull($item->fresh()->invoice_quantity);
        $this->assertFalse($order->fresh()->note_issued);
    }

    public function test_explicit_zero_total_is_valid_and_reverting_note_clears_the_entered_amount(): void
    {
        [$plan, $order] = $this->order();
        $payload = $this->notePayload($order);
        $payload['supplier_note_total'] = '0.00';
        $this->actingAs(User::factory()->create());

        $this->patchJson($this->noteUrl($plan, $order), $payload)->assertOk()->assertJsonPath('order.supplier_note_total', '0.00');

        $this->assertSame(0, DistributorInvoice::sole()->total_cents);
        $payload['note_issued'] = false;
        $payload['supplier_note_total'] = null;
        $payload['version'] = $order->fresh()->version;
        $this->patchJson($this->noteUrl($plan, $order), $payload)->assertOk()->assertJsonPath('order.supplier_note_total', '');
        $this->assertNull($order->fresh()->supplier_note_total_cents);
        $this->assertDatabaseCount('distributor_invoices', 0);
    }

    /** @return array{PurchasePlan, PurchaseOrder, PurchaseOrderItem} */
    private function order(?PurchasePlan $plan = null, string $supplierName = 'WMS'): array
    {
        $supplier = Distributor::factory()->create(['name' => $supplierName, 'city' => 'Surabaya']);
        $plan ??= PurchasePlan::factory()->create(['status' => 'finalized', 'planned_on' => '2026-10-21', 'items' => [['product_id' => null, 'product_name' => 'Yamalube 02 Oil', 'color' => 'Gold', 'product_kind' => 'daily', 'brand' => 'Yamalube', 'part_type' => 'Oli', 'current_stock' => 40, 'quantity' => 15, 'sku' => 'OIL-01', 'unit' => 'pcs', 'unit_price' => 12000, 'allocations' => []]]]);
        $items = $plan->items;
        $items[0]['allocations'][] = ['distributor_id' => $supplier->id, 'quantity' => 15];
        $items[0]['quantity'] = count($items[0]['allocations']) * 15;
        $plan->update(['items' => $items]);
        $order = PurchaseOrder::factory()->create(['purchase_plan_id' => $plan->id, 'distributor_id' => $supplier->id, 'distributor_name' => $supplierName, 'ordered_on' => '2026-10-21', 'version' => 1]);
        $item = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_name' => 'Yamalube 02 Oil', 'sku' => 'OIL-01', 'quantity' => 15, 'unit_price' => 12000]);

        return [$plan, $order, $item];
    }

    /** @return array<string, mixed> */
    private function notePayload(PurchaseOrder $order, int $quantity = 5): array
    {
        return ['version' => $order->version, 'note_issued' => true, 'supplier_note_number' => 'INV-'.$order->distributor_name.'-01', 'supplier_note_on' => '2026-10-21', 'supplier_note_total' => (string) ($quantity * 12000), 'items' => $order->items->map(fn (PurchaseOrderItem $item): array => ['id' => $item->id, 'invoice_quantity' => $quantity])->all()];
    }

    /** @return array<string, mixed> */
    private function shippingPayload(PurchaseOrder $order, ShippingService $carrier, string $origin = 'Surabaya'): array
    {
        return ['version' => $order->version, 'shipping_service_id' => $carrier->id, 'origin_port' => $origin, 'departed_on' => '2026-10-21', 'package_count' => 2];
    }

    private function noteUrl(PurchasePlan $plan, PurchaseOrder $order): string
    {
        return route('purchase-plans.orders.note', [$plan, $order]);
    }

    private function finalizeUrl(PurchasePlan $plan, PurchaseOrder $order): string
    {
        return route('purchase-plans.orders.finalize', [$plan, $order]);
    }
}
