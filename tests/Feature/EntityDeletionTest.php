<?php

namespace Tests\Feature;

use App\Models\CompetitorObservation;
use App\Models\Distributor;
use App\Models\DistributorInvoice;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\ShippingService;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntityDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'kasir']);
    }

    public function test_distributor_can_be_soft_deleted(): void
    {
        $distributor = Distributor::factory()->create([
            'name' => 'Distributor Jaya Motor',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('distributors.destroy', $distributor));

        $response->assertRedirect(route('distributors.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('distributors', [
            'id' => $distributor->id,
            'is_active' => false,
        ]);
    }

    public function test_distributor_with_active_in_progress_po_cannot_be_deleted(): void
    {
        $distributor = Distributor::factory()->create();
        $order = PurchaseOrder::create([
            'request_id' => (string) Str::uuid(),
            'reference' => 'PO-TEST-001',
            'distributor_id' => $distributor->id,
            'distributor_name' => $distributor->name,
            'ordered_on' => now()->toDateString(),
        ]);
        $order->items()->create([
            'product_name' => 'Kampas Rem',
            'sku' => 'SKU-KMP-01',
            'unit' => 'pcs',
            'quantity' => 10,
            'unit_price' => 50000,
            'actual_quantity' => null, // in-progress / unreceived
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('distributors.destroy', $distributor));

        $response->assertSessionHasErrors('distributor');
        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'deleted_at' => null,
        ]);
    }

    public function test_distributor_invoice_can_be_deleted_with_payments(): void
    {
        $invoice = DistributorInvoice::factory()->create([
            'reference' => 'INV-TEST-DEL',
            'total_cents' => 500000,
            'paid_cents' => 200000,
            'version' => 1,
        ]);

        $invoice->payments()->create([
            'request_id' => (string) Str::uuid(),
            'amount_cents' => 200000,
            'paid_on' => now()->toDateString(),
            'method' => 'transfer',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('distributor-invoices.destroy', $invoice), ['version' => 1]);

        $response->assertRedirect(route('distributor-invoices.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('distributor_invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('distributor_payments', ['distributor_invoice_id' => $invoice->id]);
    }

    public function test_distributor_invoice_delete_aborts_on_version_mismatch(): void
    {
        $invoice = DistributorInvoice::factory()->create([
            'version' => 2,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('distributor-invoices.destroy', $invoice), ['version' => 1]);

        $response->assertSessionHasErrors('version');
        $this->assertDatabaseHas('distributor_invoices', ['id' => $invoice->id]);
    }

    public function test_shipment_can_be_deleted_and_detaches_invoices(): void
    {
        $carrier = ShippingService::factory()->create();
        $shipment = Shipment::factory()->create([
            'shipping_service_id' => $carrier->id,
            'version' => 1,
        ]);

        $invoice = DistributorInvoice::factory()->create([
            'shipment_id' => $shipment->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('shipments.destroy', $shipment), ['version' => 1]);

        $response->assertRedirect(route('shipments.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shipments', ['id' => $shipment->id]);
        $this->assertDatabaseHas('distributor_invoices', [
            'id' => $invoice->id,
            'shipment_id' => null,
        ]);
    }

    public function test_shipping_service_cannot_be_deleted_when_referenced_by_shipments(): void
    {
        $carrier = ShippingService::factory()->create();
        Shipment::factory()->create(['shipping_service_id' => $carrier->id]);

        $response = $this->actingAs($this->admin)
            ->delete(route('shipping-services.destroy', $carrier));

        $response->assertSessionHasErrors('shipping_service');
        $this->assertDatabaseHas('shipping_services', ['id' => $carrier->id]);
    }

    public function test_unused_shipping_service_can_be_deleted(): void
    {
        $carrier = ShippingService::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('shipping-services.destroy', $carrier));

        $response->assertRedirect(route('shipments.index', ['services' => 1]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shipping_services', ['id' => $carrier->id]);
    }

    public function test_expense_can_be_deleted(): void
    {
        $expense = Expense::create([
            'category' => 'Listrik & Air',
            'amount' => 150000,
            'expense_date' => now()->toDateString(),
            'description' => 'Tagihan PLN bulanan',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('reports.expenses.destroy', $expense));

        $response->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_competitor_observation_can_be_deleted_with_detached_types(): void
    {
        $workshop = Workshop::create(['name' => 'Bengkel Sebelah']);
        $workType = WorkType::create(['name' => 'Ganti Oli']);

        $observation = CompetitorObservation::create([
            'workshop_id' => $workshop->id,
            'observed_on' => now()->toDateString(),
            'observed_hour' => 11,
            'created_by' => $this->admin->id,
        ]);
        $observation->workTypes()->attach($workType->id, ['quantity' => 4]);

        $response = $this->actingAs($this->admin)
            ->delete(route('reports.observations.destroy', $observation));

        $response->assertRedirect(route('reports.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('competitor_observations', ['id' => $observation->id]);
        $this->assertDatabaseMissing('competitor_observation_work_type', [
            'competitor_observation_id' => $observation->id,
        ]);
    }

    public function test_non_admin_cannot_delete_protected_entities(): void
    {
        $invoice = DistributorInvoice::factory()->create();
        $carrier = ShippingService::factory()->create();
        $shipment = Shipment::factory()->create(['shipping_service_id' => $carrier->id]);
        $expense = Expense::create([
            'category' => 'Konsumsi',
            'amount' => 50000,
            'expense_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->staff)
            ->delete(route('distributor-invoices.destroy', $invoice))
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->delete(route('shipments.destroy', $shipment))
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->delete(route('shipping-services.destroy', $carrier))
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->delete(route('reports.expenses.destroy', $expense))
            ->assertForbidden();
    }
}
