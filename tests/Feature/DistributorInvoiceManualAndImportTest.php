<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\DistributorInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class DistributorInvoiceManualAndImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_or_import_distributor_invoices(): void
    {
        $this->get(route('distributor-invoices.import.template'))->assertRedirect(route('login'));
        $this->post(route('distributor-invoices.import'))->assertRedirect(route('login'));
        $this->post(route('distributor-invoices.store'))->assertRedirect(route('login'));
    }

    public function test_admin_can_download_invoice_import_template(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('distributor-invoices.import.template'));

        $response->assertOk();
        $response->assertDownload('template-import-tagihan-distributor.xlsx');
    }

    public function test_admin_can_create_manual_invoice_with_explicit_reference(): void
    {
        $admin = User::factory()->admin()->create();
        $distributor = Distributor::factory()->create(['name' => 'PT Astra Otoparts']);

        $payload = [
            'distributor_id' => $distributor->id,
            'reference' => 'INV-ASTRA-001',
            'invoiced_on' => '2026-10-25',
            'due_on' => '2026-11-25',
            'total' => '15000000',
            'supplier_reference' => 'NOTA-9988',
            'notes' => 'Catatan faktur manual',
        ];

        $response = $this->actingAs($admin)->post(route('distributor-invoices.store'), $payload);

        $response->assertRedirect(route('distributor-invoices.index'));
        $response->assertSessionHas('success');

        $invoice = DistributorInvoice::firstOrFail();
        $this->assertSame('INV-ASTRA-001', $invoice->reference);
        $this->assertSame($distributor->id, $invoice->distributor_id);
        $this->assertNull($invoice->purchase_order_id);
        $this->assertSame(1500000000, $invoice->total_cents);
        $this->assertSame(0, $invoice->paid_cents);
        $this->assertSame('2026-10-25', $invoice->invoiced_on->toDateString());
        $this->assertSame('2026-11-25', $invoice->due_on->toDateString());
        $this->assertSame('NOTA-9988', $invoice->supplier_reference);
        $this->assertSame('Catatan faktur manual', $invoice->notes);
        $this->assertTrue($invoice->isManual());
        $this->assertSame('PT Astra Otoparts', $invoice->distributor_name);
    }

    public function test_admin_can_create_manual_invoice_with_auto_generated_reference(): void
    {
        $admin = User::factory()->admin()->create();
        $distributor = Distributor::factory()->create();

        $payload = [
            'distributor_id' => $distributor->id,
            'reference' => '',
            'invoiced_on' => '2026-10-25',
            'due_on' => '2026-11-25',
            'total' => '5000000',
        ];

        $this->actingAs($admin)->post(route('distributor-invoices.store'), $payload)
            ->assertRedirect(route('distributor-invoices.index'))
            ->assertSessionHasNoErrors();

        $invoice = DistributorInvoice::sole();
        $this->assertStringStartsWith('INV-MAN-', $invoice->reference);
    }

    public function test_manual_invoice_validation_prevents_invalid_data(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // 1. Missing distributor and due_on
        $this->post(route('distributor-invoices.store'), [])
            ->assertSessionHasErrors(['distributor_id', 'due_on', 'total']);

        // 2. Due date before invoice date
        $distributor = Distributor::factory()->create();
        $this->post(route('distributor-invoices.store'), [
            'distributor_id' => $distributor->id,
            'invoiced_on' => '2026-10-25',
            'due_on' => '2026-10-20',
            'total' => '100000',
        ])->assertSessionHasErrors('due_on');

        // 3. Duplicate reference
        DistributorInvoice::factory()->create(['reference' => 'INV-EXISTING']);
        $this->post(route('distributor-invoices.store'), [
            'distributor_id' => $distributor->id,
            'reference' => 'INV-EXISTING',
            'due_on' => '2026-11-25',
            'total' => '100000',
        ])->assertSessionHasErrors('reference');

        // 4. Zero or negative total
        $this->post(route('distributor-invoices.store'), [
            'distributor_id' => $distributor->id,
            'due_on' => '2026-11-25',
            'total' => '0',
        ])->assertSessionHasErrors('total');
    }

    public function test_admin_can_bulk_import_invoices_from_excel(): void
    {
        $admin = User::factory()->admin()->create();
        $existingDistributor = Distributor::factory()->create(['name' => 'PT Aspira Motor']);

        $rows = [
            ['INV-EXP-001', 'PT Aspira Motor', '2026-10-25', '2026-11-25', '12500000', 'NOTA-1', 'Catatan 1'],
            ['', 'Distributor Baru Jaya', '2026-10-28', '2026-12-28', '8000000', 'NOTA-2', 'Catatan 2'],
        ];

        $file = $this->createExcelFile($rows);

        $response = $this->actingAs($admin)->post(route('distributor-invoices.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('distributor-invoices.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('distributor_invoices', 2);
        $this->assertDatabaseHas('distributors', ['name' => 'Distributor Baru Jaya']);

        $inv1 = DistributorInvoice::where('reference', 'INV-EXP-001')->firstOrFail();
        $this->assertSame($existingDistributor->id, $inv1->distributor_id);
        $this->assertSame(1250000000, $inv1->total_cents);
        $this->assertSame('2026-11-25', $inv1->due_on->toDateString());

        $inv2 = DistributorInvoice::where('reference', '!=', 'INV-EXP-001')->firstOrFail();
        $this->assertStringStartsWith('INV-MAN-', $inv2->reference);
        $this->assertSame(800000000, $inv2->total_cents);
        $this->assertSame('Distributor Baru Jaya', $inv2->distributor_name);
    }

    public function test_bulk_import_rejects_invalid_rows_and_rolls_back_everything(): void
    {
        $admin = User::factory()->admin()->create();

        // Second row has due date before invoice date
        $rows = [
            ['INV-GOOD-001', 'Distributor A', '2026-10-25', '2026-11-25', '1000000', '', ''],
            ['INV-BAD-002', 'Distributor B', '2026-10-25', '2026-10-10', '2000000', '', ''],
        ];

        $file = $this->createExcelFile($rows);

        $response = $this->actingAs($admin)->post(route('distributor-invoices.import'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('distributor_invoices', 0);
        $this->assertDatabaseCount('distributors', 0);
    }

    public function test_manual_invoice_can_be_edited_paid_and_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $distributor = Distributor::factory()->create(['name' => 'PT Sumber Rejeki']);

        $invoice = DistributorInvoice::factory()->manual()->create([
            'distributor_id' => $distributor->id,
            'total_cents' => 10000000,
            'due_on' => '2026-11-20',
        ]);

        $this->actingAs($admin);

        // 1. Edit manual invoice (change due date and total)
        $this->patch(route('distributor-invoices.update', $invoice), [
            'version' => 1,
            'due_on' => '2026-11-30',
            'total' => '12000000',
            'supplier_reference' => 'NOTA-REVISI',
        ])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('2026-11-30', $invoice->due_on->toDateString());
        $this->assertSame(1200000000, $invoice->total_cents);
        $this->assertSame('NOTA-REVISI', $invoice->supplier_reference);

        // 2. Pay manual invoice
        $payPayload = [
            'request_id' => (string) Str::uuid(),
            'version' => $invoice->version,
            'amount' => '12000000',
            'paid_on' => DistributorInvoice::today()->toDateString(),
            'method' => 'transfer',
            'reference' => 'TRF-PAY-01',
        ];

        $this->post(route('distributor-invoices.pay', $invoice), $payPayload)->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->refresh()->status());
        $this->assertSame(0, $invoice->remainingCents());

        // 3. Delete manual invoice
        $this->delete(route('distributor-invoices.destroy', $invoice), ['version' => $invoice->version])
            ->assertRedirect(route('distributor-invoices.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('distributor_invoices', ['id' => $invoice->id]);
    }

    public function test_index_and_show_views_render_manual_invoices_properly(): void
    {
        $admin = User::factory()->admin()->create();
        $distributor = Distributor::factory()->create(['name' => 'PT Sentosa Abadi']);

        $invoice = DistributorInvoice::factory()->manual()->create([
            'distributor_id' => $distributor->id,
            'reference' => 'INV-MAN-TEST-99',
            'total_cents' => 5000000,
            'due_on' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($admin);

        // Index page check
        $indexResponse = $this->get(route('distributor-invoices.index'))->assertOk();
        $indexResponse->assertSee('INV-MAN-TEST-99');
        $indexResponse->assertSee('PT Sentosa Abadi');
        $indexResponse->assertSee('Manual / Tanpa PO');
        $indexResponse->assertSee('Tambah Invoice');
        $indexResponse->assertSee('Import Excel');

        // Search check
        $searchResponse = $this->get(route('distributor-invoices.index', ['search' => 'Sentosa']))->assertOk();
        $searchResponse->assertSee('INV-MAN-TEST-99');

        // Show page check
        $showResponse = $this->get(route('distributor-invoices.show', $invoice))->assertOk();
        $showResponse->assertSee('INV-MAN-TEST-99');
        $showResponse->assertSee('PT Sentosa Abadi');
        $showResponse->assertSee('Tagihan Manual');
        $showResponse->assertSee('Tagihan ini dicatat secara manual');
    }

    /** @param list<list<mixed>> $rows */
    private function createExcelFile(array $rows): UploadedFile
    {
        $admin = User::factory()->admin()->create();

        $template = $this->actingAs($admin)->get(route('distributor-invoices.import.template'))
            ->assertDownload('template-import-tagihan-distributor.xlsx')
            ->streamedContent();

        $path = tempnam(sys_get_temp_dir(), 'inv_test_');
        try {
            file_put_contents($path, $template);
            $zip = new ZipArchive;
            $zip->open($path);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $xml = '';

            foreach ($rows as $index => $row) {
                // Header in template ends around row 7-8
                $number = 10 + $index;
                $xml .= '<row r="'.$number.'">';
                foreach ($row as $colIdx => $value) {
                    $colLetter = chr(65 + $colIdx);
                    $reference = $colLetter.$number;
                    $xml .= '<c r="'.$reference.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
                }
                $xml .= '</row>';
            }

            $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('</sheetData>', $xml.'</sheetData>', $sheet));
            $zip->close();

            return UploadedFile::fake()->createWithContent('invoices.xlsx', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }
}
