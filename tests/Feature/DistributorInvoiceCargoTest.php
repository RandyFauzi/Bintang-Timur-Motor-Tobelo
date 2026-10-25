<?php

namespace Tests\Feature;

use App\Models\DistributorInvoice;
use App\Models\Shipment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DistributorInvoiceCargoTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_saves_official_invoice_date_cargo_and_claim_without_reducing_debt(): void
    {
        $invoice = DistributorInvoice::factory()->create(['due_on' => '2026-12-20']);
        $shipment = Shipment::factory()->create();
        $this->actingAs(User::factory()->create())->patch(route('distributor-invoices.update', $invoice), [
            'version' => 1, 'due_on' => '2026-12-20', 'invoiced_on' => '2026-10-19', 'shipment_id' => $shipment->id,
            'supplier_reference' => 'NOTA-123', 'discrepancy_notes' => 'Kurang 5, rusak 2. Retur ke distributor.', 'claim_amount' => '12500.25',
        ])->assertSessionHasNoErrors()->assertRedirect(route('distributor-invoices.show', $invoice));
        $this->assertSame('2026-10-19', $invoice->refresh()->invoiced_on->toDateString());
        $this->assertSame(1250025, $invoice->claim_amount_cents);
        $this->assertSame(10000000, $invoice->remainingCents());
        $this->assertSame($shipment->id, $invoice->shipment_id);
        $this->assertNull($invoice->receivedAt());
        $this->get(route('distributor-invoices.show', $invoice))->assertOk()->assertSee('19/10/2026')->assertSee('12.500,25')->assertSee('Kurang 5, rusak 2.')->assertSee('Menunggu konfirmasi barang tiba');
    }

    public function test_cargo_approval_sets_the_same_automatic_received_date_for_all_linked_invoices(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 00:30:00', 'Asia/Jayapura'));
        $shipment = Shipment::factory()->create();
        $first = DistributorInvoice::factory()->create(['shipment_id' => $shipment->id]);
        $second = DistributorInvoice::factory()->create(['shipment_id' => $shipment->id]);
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('shipments.arrive', $shipment), ['version' => 1])->assertSessionHasNoErrors();
        foreach ([$first, $second] as $invoice) {
            $this->assertSame('2026-10-20 00:30', $invoice->fresh()->receivedAt()->format('Y-m-d H:i'));
            $this->get(route('distributor-invoices.show', $invoice))->assertOk()->assertSee('20/10/2026 00:30 WIT')->assertSee($user->name);
        }
        $this->get(route('distributor-invoices.index'))->assertOk()->assertSee('Masuk: 20/10/2026');
        $this->get(route('shipments.index', ['shipment' => $shipment->id]))->assertOk()->assertSee($first->reference)->assertSee($second->reference);
        $this->travel(1)->days();
        $this->patch(route('shipments.arrive', $shipment), ['version' => 1])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-20 00:30', $first->fresh()->receivedAt()->format('Y-m-d H:i'));
    }

    public function test_linking_an_already_approved_cargo_uses_its_original_arrival_and_unlinking_clears_the_view(): void
    {
        $shipment = Shipment::factory()->create(['delivered_at' => CarbonImmutable::parse('2026-10-18 15:45:00', 'UTC')]);
        $invoice = DistributorInvoice::factory()->create();
        $this->actingAs(User::factory()->create())->patch(route('distributor-invoices.update', $invoice), [
            'version' => 1, 'due_on' => $invoice->due_on->toDateString(), 'shipment_id' => $shipment->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-19 00:45', $invoice->fresh()->receivedAt()->format('Y-m-d H:i'));
        $this->patch(route('distributor-invoices.update', $invoice), [
            'version' => 2, 'due_on' => $invoice->due_on->toDateString(), 'shipment_id' => null,
        ])->assertSessionHasNoErrors();
        $this->assertNull($invoice->fresh()->receivedAt());
        $this->assertNotNull($shipment->fresh()->delivered_at);
    }

    #[TestWith(['received_at', '2026-10-20'])]
    #[TestWith(['received_on', '2026-10-20'])]
    #[TestWith(['shipment_id', 999999])]
    #[TestWith(['claim_amount', '100000.01'])]
    #[TestWith(['claim_amount', '-1'])]
    #[TestWith(['claim_amount', '1.001'])]
    #[TestWith(['claim_amount', '1e3'])]
    public function test_invalid_details_and_manual_arrival_dates_are_rejected(string $field, string|int $value): void
    {
        $invoice = DistributorInvoice::factory()->create();
        $this->actingAs(User::factory()->create())->patchJson(route('distributor-invoices.update', $invoice), [
            'version' => 1, 'due_on' => $invoice->due_on->toDateString(), 'discrepancy_notes' => 'Klaim barang', $field => $value,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(1, $invoice->refresh()->version);
        $this->assertNull($invoice->shipment_id);
        $this->assertSame(0, $invoice->claim_amount_cents);
    }

    public function test_a_positive_claim_requires_description_and_stale_edits_cannot_replace_it(): void
    {
        $invoice = DistributorInvoice::factory()->create();
        $this->actingAs(User::factory()->create())->patchJson(route('distributor-invoices.update', $invoice), [
            'version' => 1, 'due_on' => $invoice->due_on->toDateString(), 'claim_amount' => '5000',
        ])->assertUnprocessable()->assertJsonValidationErrors('discrepancy_notes');
        $this->assertSame(0, $invoice->refresh()->claim_amount_cents);
        $invoice->increment('version');
        $this->patchJson(route('distributor-invoices.update', $invoice), [
            'version' => 1, 'due_on' => $invoice->due_on->toDateString(), 'claim_amount' => '5000', 'discrepancy_notes' => 'Klaim',
        ])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame(0, $invoice->refresh()->claim_amount_cents);
    }

    public function test_due_date_cannot_precede_an_existing_official_invoice_date_when_it_is_not_resubmitted(): void
    {
        $invoice = DistributorInvoice::factory()->create(['invoiced_on' => '2026-12-01', 'due_on' => '2026-12-31']);
        $this->actingAs(User::factory()->create())->patchJson(route('distributor-invoices.update', $invoice), [
            'version' => 1, 'due_on' => '2026-11-30',
        ])->assertUnprocessable()->assertJsonValidationErrors('due_on');
        $this->assertSame('2026-12-31', $invoice->refresh()->due_on->toDateString());
    }

    #[TestWith([8, null, null, null])]
    #[TestWith([7, 'seven_days', 2, 'Pengingat H-7'])]
    #[TestWith([4, 'seven_days', 2, 'Pengingat H-7'])]
    #[TestWith([3, 'three_days', 1, 'Pengingat H-3'])]
    #[TestWith([1, 'three_days', 1, 'Pengingat H-3'])]
    #[TestWith([0, 'today', 1, 'Hari H:'])]
    #[TestWith([-1, 'overdue', 0, 'Pembayaran melewati jatuh tempo'])]
    public function test_reminders_escalate_at_h7_h3_and_due_day_using_tobelo_time(int $days, ?string $stage, ?int $priority, ?string $text): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 00:30:00', 'Asia/Jayapura'));
        $invoice = DistributorInvoice::factory()->create(['due_on' => DistributorInvoice::today()->addDays($days)->toDateString()]);
        $response = $this->actingAs(User::factory()->create())->get(route('notifications.index', ['type' => $days < 0 ? 'invoice_overdue' : 'invoice_due']))->assertOk();
        $this->assertSame($stage, $invoice->reminderStage());
        $this->assertSame($stage === null ? 0 : 1, $response->viewData('notifications')->total());
        if ($text !== null) {
            $response->assertSee($text);
            $this->assertSame($priority, (int) $response->viewData('notifications')->first()->priority);
        }
        $invoice->paid_cents = $invoice->total_cents;
        $invoice->save();
        $this->assertNull($invoice->reminderStage());
        $this->getJson(route('shipments.notifications'))->assertJsonPath('invoice_count', 0);
    }

    public function test_overdue_status_keeps_partial_payments_visible_and_totals_ignore_unapproved_claims(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 08:00:00', 'Asia/Jayapura'));
        $invoice = DistributorInvoice::factory()->create(['due_on' => '2026-10-19', 'paid_cents' => 2500000, 'claim_amount_cents' => 1000000]);
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('distributor-invoices.index', ['status' => 'overdue']))->assertOk()->assertSee('Terlambat')->assertSee('Dibayar Sebagian');
        $this->assertSame(1, $response->viewData('invoices')->total());
        $this->assertSame(10000000, $response->viewData('summary')['total']);
        $this->assertSame(2500000, $response->viewData('summary')['paid']);
        $this->assertSame(7500000, $response->viewData('summary')['outstanding']);
        $this->assertSame('overdue', $invoice->billingStatus());
        $this->assertSame('partial', $invoice->status());
        $this->get(route('distributor-invoices.show', $invoice))->assertSee('Nilai Klaim Dicatat');
    }

    public function test_cargo_migration_can_resume_without_resetting_existing_invoice_details(): void
    {
        $shipment = Shipment::factory()->create();
        $invoice = DistributorInvoice::factory()->create([
            'shipment_id' => $shipment->id, 'invoiced_on' => '2026-10-18',
            'discrepancy_notes' => 'Barang rusak', 'claim_amount_cents' => 500025,
        ]);
        $migration = require database_path('migrations/2026_10_20_075108_add_cargo_and_document_details_to_distributor_invoices.php');
        $migration->up();
        $this->assertSame($shipment->id, $invoice->refresh()->shipment_id);
        $this->assertSame('2026-10-18', $invoice->invoiced_on->toDateString());
        $this->assertSame(500025, $invoice->claim_amount_cents);
        $this->assertSame('Barang rusak', $invoice->discrepancy_notes);
        $this->assertSame(10000000, $invoice->total_cents);
    }

    public function test_invoice_table_shows_return_notes_and_direct_edit_tempo_link(): void
    {
        $invoice = DistributorInvoice::factory()->create(['discrepancy_notes' => 'Retur 2 pcs dan kurang 5 pcs']);
        $this->actingAs(User::factory()->create())->get(route('distributor-invoices.index'))->assertOk()
            ->assertSee('Retur 2 pcs dan kurang 5 pcs')->assertSee('Edit Data')->assertSee('Bayar')
            ->assertSee(route('distributor-invoices.show', ['distributorInvoice' => $invoice->id, 'edit' => 1]), false)
            ->assertSee(route('distributor-invoices.show', ['distributorInvoice' => $invoice->id, 'pay' => 1]), false);
        $this->get(route('distributor-invoices.show', ['distributorInvoice' => $invoice->id, 'edit' => 1]))->assertOk()
            ->assertSee('appDialog(true)', false)->assertSee('Jatuh Tempo');
    }

    public function test_claim_notes_are_escaped_and_blank_legacy_invoice_dates_are_not_invented(): void
    {
        $invoice = DistributorInvoice::factory()->create(['discrepancy_notes' => '<script>alert(1)</script>']);
        $this->actingAs(User::factory()->create())->get(route('distributor-invoices.show', $invoice))->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('Belum diisi sesuai dokumen distributor');
        $this->assertNull($invoice->invoiced_on);
    }
}
