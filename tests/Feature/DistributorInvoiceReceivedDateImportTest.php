<?php

namespace Tests\Feature;

use App\Models\DistributorInvoice;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;
use ZipArchive;

class DistributorInvoiceReceivedDateImportTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['2026-10-28'])]
    #[TestWith(['28/10/2026'])]
    #[TestWith(['46323'])]
    public function test_import_saves_receipt_date_and_displays_it_on_invoice(string $date): void
    {
        $file = $this->createExcelFile([['RECEIPT-1', 'Distributor A', $date, '2026-11-30', '120000', '', '']]);
        $this->post(route('distributor-invoices.import'), ['file' => $file])->assertSessionHasNoErrors();
        $invoice = DistributorInvoice::where('reference', 'RECEIPT-1')->firstOrFail();
        $this->assertSame('2026-10-28', $invoice->received_on->toDateString());
        $this->assertSame('2026-10-28', $invoice->receivedAt()->toDateString());
        $this->get(route('distributor-invoices.index'))->assertOk()->assertSee('28/10/2026');
    }

    public function test_invalid_receipt_date_rolls_back_the_entire_import(): void
    {
        $file = $this->createExcelFile([
            ['VALID', 'New Distributor', '2026-10-28', '2026-11-30', '1000', '', ''],
            ['INVALID', 'Other Distributor', '31/02/2026', '2026-11-30', '1000', '', ''],
        ]);
        $this->post(route('distributor-invoices.import'), ['file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('distributor_invoices', 0);
        $this->assertDatabaseCount('distributors', 0);
    }

    public function test_old_template_without_receipt_column_still_imports(): void
    {
        $file = $this->createExcelFile([['LEGACY', 'Distributor A', '', '2026-11-30', '1000', '', '']]);
        $this->post(route('distributor-invoices.import'), ['file' => $file])->assertSessionHasNoErrors();
        $this->assertNull(DistributorInvoice::firstOrFail()->receivedAt());
    }

    public function test_linked_shipment_remains_the_authoritative_receipt_date(): void
    {
        $shipment = Shipment::factory()->create(['delivered_at' => null]);
        $invoice = DistributorInvoice::factory()->manual()->create(['received_on' => null, 'shipment_id' => $shipment->id]);
        $this->assertNull($invoice->receivedAt());
        $shipment->forceFill(['delivered_at' => '2026-10-29 12:00:00'])->save();
        $invoice->refresh();
        $this->assertSame('2026-10-29', $invoice->receivedAt()->toDateString());

        $invoice->update(['received_on' => '2026-10-31']);
        $this->assertSame('2026-10-31', $invoice->fresh()->receivedAt()->toDateString());
    }

    private function createExcelFile(array $rows, array $extraEntries = [], bool $legacy = false): UploadedFile
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
            if ($legacy) {
                $document = new \DOMDocument;
                $document->loadXML($sheet);
                $xpath = new \DOMXPath($document);
                foreach ($xpath->query('//*[local-name()="c" and starts-with(@r, "H")]') as $cell) {
                    $cell->parentNode->removeChild($cell);
                }
                $sheet = $document->saveXML();
            }
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
            foreach ($extraEntries as $name => $contents) {
                $zip->addFromString($name, $contents);
            }
            $zip->close();

            return UploadedFile::fake()->createWithContent('invoices.xlsx', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }
}
