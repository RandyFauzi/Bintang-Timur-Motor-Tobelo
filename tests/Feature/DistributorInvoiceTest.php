<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Distributor;
use App\Models\DistributorInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DistributorInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_or_pay_distributor_invoices(): void
    {
        $invoice = DistributorInvoice::factory()->create();
        $this->get(route('distributor-invoices.index'))->assertRedirect(route('login'));
        $this->get(route('distributor-invoices.show', $invoice))->assertRedirect(route('login'));
        $this->patch(route('distributor-invoices.update', $invoice))->assertRedirect(route('login'));
        $this->post(route('distributor-invoices.pay', $invoice))->assertRedirect(route('login'));
    }

    #[TestWith([null, null, '2026-04-30'])]
    #[TestWith([2, null, '2026-03-31'])]
    #[TestWith([3, '2026-05-15', '2026-05-15'])]
    public function test_po_automatically_creates_one_invoice_with_exact_total_and_payment_term(?int $months, ?string $dueOn, string $expected): void
    {
        $distributor = Distributor::factory()->create();
        $payload = ['request_id' => (string) Str::uuid(), 'distributor_id' => $distributor->id, 'ordered_on' => '2026-01-31', 'payment_term_months' => $months, 'due_on' => $dueOn, 'items' => [['product_name' => 'Nama Barang Distributor', 'unit' => 'pcs', 'quantity' => 3, 'unit_price' => '123.45']]];
        $this->actingAs(User::factory()->create())->post(route('purchase-orders.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $invoice = DistributorInvoice::sole();
        $this->assertSame('unpaid', $invoice->status());
        $this->assertSame(37035, $invoice->total_cents);
        $this->assertSame($expected, $invoice->due_on->toDateString());
        $this->assertSame(0, $invoice->paid_cents);
        $this->post(route('purchase-orders.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('distributor_invoices', 1);
        $this->get(route('purchase-orders.show', $invoice->purchase_order_id))->assertSee(route('distributor-invoices.show', $invoice), false);
    }

    public function test_partial_payment_and_settlement_update_balance_and_record_history_and_audit(): void
    {
        $invoice = DistributorInvoice::factory()->create(['total_cents' => 1000050]);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('distributor-invoices.pay', $invoice), $this->payment('2500.25'))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(750025, $invoice->refresh()->remainingCents());
        $this->assertSame('partial', $invoice->status());
        $this->assertDatabaseHas('distributor_payments', ['distributor_invoice_id' => $invoice->id, 'amount_cents' => 250025, 'created_by' => $user->id]);
        $this->get(route('distributor-invoices.show', $invoice))->assertOk()->assertSee('Dibayar Sebagian')->assertSee('2.500,25');
        $audit = ActivityLog::where('route_name', 'distributor-invoices.pay')->latest('id')->firstOrFail();
        $this->assertContains('DistributorPayment', array_column($audit->changes, 'entity'));
        $this->post(route('distributor-invoices.pay', $invoice), $this->payment('7500.25', 2))->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->refresh()->status());
        $this->assertSame(0, $invoice->remainingCents());
        $this->assertDatabaseCount('distributor_payments', 2);
        $this->get(route('distributor-invoices.show', $invoice))->assertSee('Lunas')->assertDontSee('Catat Pembayaran');
    }

    public function test_replayed_payment_is_not_counted_twice_and_changed_payload_is_rejected(): void
    {
        $invoice = DistributorInvoice::factory()->create();
        $payload = $this->payment('25000.00');
        $this->actingAs(User::factory()->create())->post(route('distributor-invoices.pay', $invoice), $payload)->assertSessionHasNoErrors();
        $this->post(route('distributor-invoices.pay', $invoice), $payload)->assertSessionHasNoErrors();
        $payload['amount'] = '30000';
        $this->postJson(route('distributor-invoices.pay', $invoice), $payload)->assertUnprocessable()->assertJsonValidationErrors('request_id');
        $this->assertSame(2500000, $invoice->refresh()->paid_cents);
        $this->assertDatabaseCount('distributor_payments', 1);
    }

    #[TestWith(['100000.01'])]
    #[TestWith(['0'])]
    #[TestWith(['-1'])]
    #[TestWith(['1.001'])]
    #[TestWith(['1e3'])]
    public function test_invalid_amount_or_overpayment_does_not_change_invoice(string $amount): void
    {
        $invoice = DistributorInvoice::factory()->create();
        $this->actingAs(User::factory()->create())->postJson(route('distributor-invoices.pay', $invoice), $this->payment($amount))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertSame(0, $invoice->refresh()->paid_cents);
        $this->assertDatabaseCount('distributor_payments', 0);
    }

    public function test_stale_payment_and_stale_due_date_edits_cannot_overwrite_each_other(): void
    {
        $invoice = DistributorInvoice::factory()->create();
        $newDue = $invoice->due_on->addDays(2)->toDateString();
        $this->actingAs(User::factory()->create())->patch(route('distributor-invoices.update', $invoice), ['version' => 1, 'due_on' => $newDue, 'supplier_reference' => 'NOTA-123'])->assertSessionHasNoErrors();
        $this->postJson(route('distributor-invoices.pay', $invoice), $this->payment('10000'))->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->patchJson(route('distributor-invoices.update', $invoice), ['version' => 1, 'due_on' => $newDue])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame($newDue, $invoice->refresh()->due_on->toDateString());
        $this->assertSame('NOTA-123', $invoice->supplier_reference);
        $this->assertDatabaseCount('distributor_payments', 0);
    }

    public function test_payment_dates_before_order_or_in_future_are_rejected(): void
    {
        $invoice = DistributorInvoice::factory()->create();
        $this->actingAs(User::factory()->create());
        foreach ([now()->subDay()->toDateString(), now()->addDays(2)->toDateString()] as $date) {
            $data = $this->payment('10');
            $data['paid_on'] = $date;
            $this->postJson(route('distributor-invoices.pay', $invoice), $data)->assertUnprocessable()->assertJsonValidationErrors('paid_on');
        }
        $this->assertDatabaseCount('distributor_payments', 0);
    }

    public function test_due_date_cannot_be_before_order(): void
    {
        $invoice = DistributorInvoice::factory()->create();
        $this->actingAs(User::factory()->create())->patchJson(route('distributor-invoices.update', $invoice), ['version' => 1, 'due_on' => $invoice->purchaseOrder->ordered_on->subDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('due_on');
        $this->assertSame(1, $invoice->refresh()->version);
    }

    public function test_notifications_include_unpaid_and_partial_but_exclude_paid_and_future_invoices(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-17 00:30:00', 'Asia/Jayapura'));
        $order = PurchaseOrder::factory()->create(['ordered_on' => '2026-01-01', 'distributor_name' => 'Supplier Mendekat']);
        $near = DistributorInvoice::factory()->create(['purchase_order_id' => $order->id, 'due_on' => '2026-10-24', 'total_cents' => 10000000]);
        $late = DistributorInvoice::factory()->create(['due_on' => '2026-10-16', 'paid_cents' => 5000000]);
        $today = DistributorInvoice::factory()->create(['due_on' => '2026-10-17']);
        DistributorInvoice::factory()->create(['due_on' => '2026-10-25']);
        DistributorInvoice::factory()->create(['due_on' => '2026-10-10', 'paid_cents' => 10000000]);
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('notifications.index', ['type' => 'invoice_due']))->assertOk()->assertSee('Supplier Mendekat')->assertSee(route('distributor-invoices.show', $near), false);
        $this->assertSame(2, $response->viewData('counts')['invoice_due']);
        $this->assertSame(1, $response->viewData('counts')['invoice_overdue']);
        $this->getJson(route('shipments.notifications'))->assertJsonPath('invoice_count', 3)->assertJsonPath('active_count', 3);
        $this->get(route('dashboard'))->assertSee('aria-live="polite">3</span>', false);
        $this->assertSame('Terlambat 1 hari', $late->timingLabel());
        $this->assertSame('Jatuh tempo hari ini', $today->timingLabel());
        $data = $this->payment('100000');
        $data['paid_on'] = '2026-10-17';
        $this->post(route('distributor-invoices.pay', $near), $data)->assertSessionHasNoErrors();
        $this->getJson(route('shipments.notifications'))->assertJsonPath('invoice_count', 2)->assertJsonPath('active_count', 2);
    }

    public function test_invoice_filters_and_summaries_handle_all_payment_states(): void
    {
        $first = PurchaseOrder::factory()->create(['distributor_name' => 'Pemasok Fokus']);
        $unpaid = DistributorInvoice::factory()->create(['purchase_order_id' => $first->id, 'reference' => 'INV-FOKUS', 'due_on' => now()->subDay()->toDateString()]);
        DistributorInvoice::factory()->create(['paid_cents' => 2500000]);
        DistributorInvoice::factory()->create(['paid_cents' => 10000000]);
        $this->actingAs(User::factory()->create());
        foreach (['unpaid', 'partial', 'paid'] as $status) {
            $response = $this->get(route('distributor-invoices.index', ['status' => $status]))->assertOk();
            $this->assertSame(1, $response->viewData('invoices')->total());
        }
        $response = $this->get(route('distributor-invoices.index', ['search' => 'FOKUS']))->assertOk()->assertSee('Pemasok Fokus');
        $this->assertSame(10000000, $response->viewData('summary')['outstanding']);
        $this->assertSame(10000000, $response->viewData('summary')['overdue']);
        $this->assertSame($unpaid->id, $response->viewData('invoices')->first()->id);
    }

    public function test_multiple_payment_statuses_are_combined_without_duplicates_and_keep_search_constraints(): void
    {
        $unpaid = DistributorInvoice::factory()->create(['reference' => 'MULTI-UNPAID', 'due_on' => DistributorInvoice::today()->subDay()->toDateString()]);
        $partial = DistributorInvoice::factory()->create(['reference' => 'MULTI-PARTIAL', 'paid_cents' => 2500000]);
        DistributorInvoice::factory()->create(['reference' => 'MULTI-PAID', 'paid_cents' => 10000000]);
        DistributorInvoice::factory()->create(['reference' => 'EXCLUDED-UNPAID']);
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('distributor-invoices.index', ['search' => 'MULTI-', 'status' => ['unpaid', 'partial', 'overdue']]))->assertOk();
        $this->assertEqualsCanonicalizing([$unpaid->id, $partial->id], $response->viewData('invoices')->modelKeys());
        $this->getJson(route('distributor-invoices.index', ['status' => ['unpaid', 'invalid']]))
            ->assertUnprocessable()->assertJsonValidationErrors('status.1');
        $this->assertSame(4, $this->get(route('distributor-invoices.index', ['status' => []]))->assertOk()->viewData('invoices')->total());
    }

    public function test_due_timing_filter_and_perlu_dibayar_status_on_due_date(): void
    {
        $today = DistributorInvoice::today();
        $this->travelTo($today->setTime(8, 0, 0));

        $dueToday = DistributorInvoice::factory()->create([
            'reference' => 'INV-TODAY',
            'due_on' => $today->toDateString(),
            'total_cents' => 10000000,
            'paid_cents' => 0,
        ]);
        $dueOneMonth = DistributorInvoice::factory()->create([
            'reference' => 'INV-1M',
            'due_on' => $today->addDays(20)->toDateString(),
        ]);
        $dueTwoMonths = DistributorInvoice::factory()->create([
            'reference' => 'INV-2M',
            'due_on' => $today->addDays(45)->toDateString(),
        ]);

        $this->assertSame('overdue', $dueToday->billingStatus());
        $this->assertSame('Perlu Dibayar', DistributorInvoice::STATUSES[$dueToday->billingStatus()]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('distributor-invoices.index'))->assertOk();
        $response->assertSee('Perlu Dibayar');
        $response->assertSee(route('distributor-invoices.show', ['distributorInvoice' => $dueToday->id, 'pay' => 1]), false);
        $response->assertSee(route('distributor-invoices.show', ['distributorInvoice' => $dueToday->id, 'edit' => 1]), false);

        $res1m = $this->get(route('distributor-invoices.index', ['timing' => '1']))->assertOk();
        $this->assertTrue($res1m->viewData('invoices')->contains($dueToday));
        $this->assertTrue($res1m->viewData('invoices')->contains($dueOneMonth));
        $this->assertFalse($res1m->viewData('invoices')->contains($dueTwoMonths));

        $res2m = $this->get(route('distributor-invoices.index', ['timing' => '2']))->assertOk();
        $this->assertFalse($res2m->viewData('invoices')->contains($dueToday));
        $this->assertFalse($res2m->viewData('invoices')->contains($dueOneMonth));
        $this->assertTrue($res2m->viewData('invoices')->contains($dueTwoMonths));

        $this->get(route('distributor-invoices.show', ['distributorInvoice' => $dueToday->id, 'pay' => 1]))->assertOk()
            ->assertSee('Catat Pembayaran')
            ->assertSee('appDialog(true)', false);

        $this->get(route('distributor-invoices.show', ['distributorInvoice' => $dueToday->id, 'edit' => 1]))->assertOk()
            ->assertSee('Edit Faktur, Kargo & Tempo')
            ->assertSee('appDialog(true)', false);
    }

    public function test_backfill_generates_exactly_one_invoice_for_existing_orders(): void
    {
        $item = PurchaseOrderItem::factory()->create(['quantity' => 3, 'unit_price' => '123.45']);
        $migration = require database_path('migrations/2026_10_17_084109_backfill_purchase_order_invoices.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseCount('distributor_invoices', 1);
        $this->assertDatabaseHas('distributor_invoices', ['purchase_order_id' => $item->purchase_order_id, 'total_cents' => 37035]);
    }

    public function test_invoice_and_po_roll_back_if_total_exceeds_supported_limit(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('purchase-orders.store'), ['request_id' => (string) Str::uuid(), 'distributor_id' => Distributor::factory()->create()->id, 'ordered_on' => now()->toDateString(), 'items' => [['product_name' => 'Nama Barang Distributor', 'unit' => 'pcs', 'quantity' => 1000000, 'unit_price' => '999999999']]])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseCount('distributor_invoices', 0);
    }

    /** @return array<string,mixed> */
    private function payment(string $amount, int $version = 1): array
    {
        return ['request_id' => (string) Str::uuid(), 'version' => $version, 'amount' => $amount, 'paid_on' => DistributorInvoice::today()->toDateString(), 'method' => 'transfer', 'reference' => 'TRF-123'];
    }
}
