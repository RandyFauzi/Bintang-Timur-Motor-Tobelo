<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\DistributorInvoice;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class StaffPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_access_laporan_po_invoice_pengiriman_user_management_and_activity_logs(): void
    {
        $staff = User::factory()->staff()->create();
        $otherUser = User::factory()->create();
        $order = PurchaseOrder::factory()->create();
        $invoice = DistributorInvoice::factory()->create();
        $shipment = Shipment::factory()->create();

        $this->actingAs($staff);

        // 1. Laporan
        $this->get(route('reports.finance'))->assertForbidden();
        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('reports.export'))->assertForbidden();
        $this->post(route('reports.expenses.store'), [])->assertForbidden();
        $this->post(route('reports.observations.store'), [])->assertForbidden();

        // 2. PO (Purchase Orders & Plans)
        $this->get(route('purchase-orders.index'))->assertForbidden();
        $this->get(route('purchase-orders.create'))->assertForbidden();
        $this->post(route('purchase-orders.store'), [])->assertForbidden();
        $this->get(route('purchase-orders.show', $order))->assertForbidden();
        $this->get(route('purchase-plans.products'))->assertForbidden();

        // 3. Invoice (Distributor Invoices)
        $this->get(route('distributor-invoices.index'))->assertForbidden();
        $this->get(route('distributor-invoices.show', $invoice))->assertForbidden();
        $this->patch(route('distributor-invoices.update', $invoice), [])->assertForbidden();
        $this->post(route('distributor-invoices.pay', $invoice), [])->assertForbidden();

        // 4. Pengiriman (Shipments & Services)
        $this->get(route('shipments.index'))->assertForbidden();
        $this->post(route('shipments.store'), [])->assertForbidden();
        $this->patch(route('shipments.update', $shipment), [])->assertForbidden();
        $this->patch(route('shipments.arrive', $shipment), [])->assertForbidden();
        $this->post(route('shipping-services.store'), [])->assertForbidden();

        // 5. Edit User, 6. Log Aktivitas, 7. List Pengguna
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.export'))->assertForbidden();
        $this->post(route('users.store'), [])->assertForbidden();
        $this->patch(route('users.update', $otherUser), [])->assertForbidden();
        $this->delete(route('users.destroy', $otherUser))->assertForbidden();
        $this->get(route('activity-logs.index'))->assertForbidden();
        $this->get(route('activity-logs.export'))->assertForbidden();
    }

    public function test_staff_can_access_allowed_modules(): void
    {
        $staff = User::factory()->staff()->create();
        $distributor = Distributor::factory()->create();

        $this->actingAs($staff);

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('pos.index'))->assertOk();
        $this->get(route('products.index'))->assertOk();
        $this->get(route('distributors.index'))->assertOk();
        $this->get(route('returns.index'))->assertOk();
        $this->get(route('transactions.index'))->assertOk();
        $this->get(route('notifications.index'))->assertOk();
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_sidebar_hides_restricted_menus_for_staff_and_shows_them_for_admin(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();

        // Staff sidebar
        $response = $this->actingAs($staff)->get(route('dashboard'))->assertOk();
        $response->assertSee(route('pos.index'), false);
        $response->assertSee(route('products.index'), false);
        $response->assertSee(route('distributors.index'), false);
        $response->assertSee(route('returns.index'), false);
        $response->assertSee(route('transactions.index'), false);

        // Restricted modules must NOT be in staff sidebar
        $response->assertDontSee(route('purchase-orders.index'), false);
        $response->assertDontSee(route('shipments.index'), false);
        $response->assertDontSee(route('distributor-invoices.index'), false);
        $response->assertDontSee(route('reports.finance'), false);
        $response->assertDontSee(route('reports.index'), false);
        $response->assertDontSee(route('users.index'), false);
        $response->assertDontSee(route('activity-logs.index'), false);

        // Admin sidebar shows everything
        $adminResponse = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $adminResponse->assertSee(route('purchase-orders.index'), false);
        $adminResponse->assertSee(route('shipments.index'), false);
        $adminResponse->assertSee(route('distributor-invoices.index'), false);
        $adminResponse->assertSee(route('reports.finance'), false);
        $adminResponse->assertSee(route('reports.index'), false);
        $adminResponse->assertSee(route('users.index'), false);
        $adminResponse->assertSee(route('activity-logs.index'), false);
    }

    public function test_view_sales_metrics_gate_allows_admin_and_denies_staff(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('view-sales-metrics'));
        $this->assertFalse(Gate::forUser($staff)->allows('view-sales-metrics'));
    }
}
