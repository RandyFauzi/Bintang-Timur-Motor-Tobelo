<?php

namespace Tests\Feature;

use App\Models\DistributorInvoice;
use App\Models\DistributorPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributorInvoiceBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_delete_removes_selected_invoices_and_payments_but_preserves_other_records(): void
    {
        $first = DistributorInvoice::factory()->manual()->create();
        $second = DistributorInvoice::factory()->create();
        $other = DistributorInvoice::factory()->create();
        $payment = DistributorPayment::factory()->create(['distributor_invoice_id' => $first->id]);
        $order = $second->purchaseOrder;
        $this->actingAs(User::factory()->admin()->create())->delete(route('distributor-invoices.bulk.destroy'), ['selected' => [
            ['id' => $first->id, 'version' => 1], ['id' => $second->id, 'version' => 1],
        ]])->assertRedirect(route('distributor-invoices.index'))->assertSessionHasNoErrors();
        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertModelMissing($payment);
        $this->assertModelExists($other);
        $this->assertModelExists($order);
    }

    public function test_stale_or_missing_invoice_aborts_the_entire_deletion(): void
    {
        $first = DistributorInvoice::factory()->create();
        $second = DistributorInvoice::factory()->create(['version' => 2]);
        $payment = DistributorPayment::factory()->create(['distributor_invoice_id' => $first->id]);
        $this->actingAs(User::factory()->admin()->create());
        foreach ([['id' => $second->id, 'version' => 1], ['id' => 999999, 'version' => 1]] as $invalid) {
            $this->deleteJson(route('distributor-invoices.bulk.destroy'), ['selected' => [['id' => $first->id, 'version' => 1], $invalid]])->assertUnprocessable();
            $this->assertModelExists($first);
            $this->assertModelExists($second);
            $this->assertModelExists($payment);
        }
    }

    public function test_invalid_selections_and_unauthorized_requests_are_rejected(): void
    {
        $invoice = DistributorInvoice::factory()->create();
        $this->delete(route('distributor-invoices.bulk.destroy'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->staff()->create())->deleteJson(route('distributor-invoices.bulk.destroy'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create());
        $row = ['id' => $invoice->id, 'version' => 1];
        foreach ([[], [$row, $row], [['id' => $invoice->id]], array_fill(0, 101, $row)] as $selected) {
            $this->deleteJson(route('distributor-invoices.bulk.destroy'), ['selected' => $selected])->assertUnprocessable();
        }
        $this->assertModelExists($invoice);
    }

    public function test_scroll_response_contains_only_filtered_rows_and_preserves_next_page_filters(): void
    {
        DistributorInvoice::factory()->manual()->count(31)->create(['notes' => 'Loaded']);
        DistributorInvoice::factory()->manual()->create(['paid_cents' => 10000000]);
        $this->actingAs(User::factory()->admin()->create());
        $response = $this->get(route('distributor-invoices.index', ['status' => ['unpaid'], 'page' => 2]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertViewIs('distributor-invoices.list')->assertDontSee('<html', false);
        $this->assertCount(15, $response->viewData('invoices'));
        $this->assertSame(31, $response->viewData('invoices')->total());
        $this->assertStringContainsString('status', $response->viewData('invoices')->nextPageUrl());
        $response->assertSee('data-invoice-version', false)->assertSee('Halaman berikutnya');
    }
}
