<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\DistributorInvoice;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributorInvoiceManualReceivedDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_invoice_receipt_date_can_be_created_edited_and_cleared(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('distributor-invoices.store'), [
            'distributor_id' => Distributor::factory()->create()->id,
            'invoiced_on' => '2026-10-01', 'due_on' => '2026-11-30',
            'total' => '10000', 'received_on' => '2026-10-28',
        ])->assertSessionHasNoErrors();
        $invoice = DistributorInvoice::firstOrFail();
        $this->assertSame('2026-10-28', $invoice->receivedAt()->toDateString());
        $this->get(route('distributor-invoices.index'))->assertOk()->assertSee('name="received_on"', false);
        $this->get(route('distributor-invoices.show', $invoice))->assertOk()->assertSee('value="2026-10-28"', false);
        foreach (['2026-10-29', null] as $date) {
            $this->patch(route('distributor-invoices.update', $invoice), [
                'version' => $invoice->version, 'due_on' => '2026-11-30', 'received_on' => $date,
            ])->assertSessionHasNoErrors();
            $invoice->refresh();
            $this->assertSame($date, $invoice->receivedAt()?->toDateString());
        }
    }

    public function test_invalid_or_stale_edits_do_not_replace_receipt_dates(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $invoice = DistributorInvoice::factory()->manual()->create(['received_on' => '2026-10-28', 'version' => 2]);
        foreach ([
            ['version' => 2, 'received_on' => '2026-02-31'],
            ['version' => 1, 'received_on' => '2026-10-29'],
        ] as $data) {
            $this->patchJson(route('distributor-invoices.update', $invoice), $data + [
                'due_on' => $invoice->due_on->toDateString(),
            ])->assertUnprocessable();
            $this->assertSame('2026-10-28', $invoice->fresh()->receivedAt()->toDateString());
        }
    }

    public function test_linked_shipments_cannot_have_receipt_dates_overridden(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $shipment = Shipment::factory()->create();
        $invoice = DistributorInvoice::factory()->manual()->create(['shipment_id' => $shipment->id]);
        $this->patchJson(route('distributor-invoices.update', $invoice), [
            'version' => 1, 'due_on' => $invoice->due_on->toDateString(), 'received_on' => '2026-10-28',
        ])->assertUnprocessable()->assertJsonValidationErrors('received_on');
        $this->postJson(route('distributor-invoices.store'), [
            'distributor_id' => Distributor::factory()->create()->id,
            'invoiced_on' => '2026-10-01', 'due_on' => '2026-11-30', 'total' => '1000',
            'shipment_id' => $shipment->id, 'received_on' => '2026-10-28',
        ])->assertUnprocessable()->assertJsonValidationErrors('received_on');
        $this->assertNull($invoice->fresh()->received_on);
    }
}
