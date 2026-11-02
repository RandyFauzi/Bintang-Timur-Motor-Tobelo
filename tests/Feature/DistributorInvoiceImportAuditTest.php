<?php

namespace Tests\Feature;

use App\Models\DistributorInvoice;
use App\Models\User;
use App\Services\DistributorInvoiceImportService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

class DistributorInvoiceImportAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_not_updated_is_a_visible_error_without_any_writes(): void
    {
        $file = $this->createExcelFile([['TEST', 'New Supplier', '2026-10-01', '2026-11-30', '1000']]);
        Schema::table('distributor_invoices', fn ($table) => $table->dropColumn('received_on'));
        $this->post(route('distributor-invoices.import'), ['file' => $file])
            ->assertRedirect(route('distributor-invoices.index'))->assertSessionHasErrors('file')
            ->assertSessionHasInput('_dialog', 'invoice-import');
        $this->assertStringContainsString('database server belum diperbarui', session('errors')->first('file'));
        $this->assertDatabaseCount('distributor_invoices', 0);
        $this->assertDatabaseCount('distributors', 0);
    }

    public function test_duplicate_headers_and_formula_errors_are_rejected_before_writing(): void
    {
        foreach ([
            fn ($xml) => str_replace('Catatan', 'Total Tagihan', $xml),
            fn ($xml) => str_replace('<c r="E10" t="inlineStr"><is><t>1000</t></is></c>', '<c r="E10" t="e"><v>#VALUE!</v></c>', $xml),
            fn ($xml) => str_replace('<c r="E10" t="inlineStr"><is><t>1000</t></is></c>', '<c r="E10"><f>1+1</f></c>', $xml),
        ] as $transform) {
            $file = $this->createExcelFile([['TEST', 'New Supplier', '2026-10-01', '2026-11-30', '1000']], transform: $transform);
            $this->post(route('distributor-invoices.import'), ['file' => $file])->assertSessionHasErrors('file');
            $this->assertDatabaseCount('distributor_invoices', 0);
            $this->assertDatabaseCount('distributors', 0);
        }
    }

    public function test_excel_1904_dates_and_time_fractions_are_imported_correctly(): void
    {
        $file = $this->createExcelFile([['TEST', 'Supplier', '44861.5', '44900', '1000']], [
            'xl/workbook.xml' => '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr date1904="1"/><sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets></workbook>',
        ]);
        $this->post(route('distributor-invoices.import'), ['file' => $file])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-28', DistributorInvoice::where('reference', 'TEST')->firstOrFail()->invoiced_on->toDateString());
    }

    public function test_database_errors_are_reported_without_showing_sql_to_the_user(): void
    {
        $file = $this->createExcelFile([['TEST', 'Supplier', '2026-10-01', '2026-11-30', '1000']]);
        $this->mock(DistributorInvoiceImportService::class)->shouldReceive('import')->once()
            ->andThrow(new QueryException('sqlite', 'secret SQL', [], new \PDOException('internal error')));
        $this->post(route('distributor-invoices.import'), ['file' => $file])->assertSessionHasErrors('file');
        $this->assertStringNotContainsString('secret SQL', session('errors')->first('file'));
        $this->assertDatabaseCount('distributor_invoices', 0);
    }

    private function createExcelFile(array $rows, array $extraEntries = [], bool $legacy = false, ?callable $transform = null): UploadedFile
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

            $sheet = str_replace('</sheetData>', $xml.'</sheetData>', $sheet);
            $zip->addFromString('xl/worksheets/sheet1.xml', $transform ? $transform($sheet) : $sheet);
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
