<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_export_returns_valid_styled_xlsx_spreadsheet(): void
    {
        $user = User::factory()->create();
        $distributor = Distributor::factory()->create(['name' => 'Lautan Berlian']);
        $product = Product::factory()->withStock(15)->create([
            'name' => 'Oli Enduro Racing',
            'purchase_price' => 45000,
            'selling_price' => 58000,
            'product_kind' => 'daily',
        ]);
        $product->distributors()->attach($distributor);

        $response = $this->actingAs($user)->get(route('products.export', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertDownload('produk-'.now()->format('Ymd').'.xlsx');

        $content = $response->streamedContent();
        $tempPath = tempnam(sys_get_temp_dir(), 'test_xlsx_assert_');
        file_put_contents($tempPath, $content);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempPath));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));
        $this->assertNotFalse($zip->locateName('xl/styles.xml'));
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('BINTANG TIMUR MOTOR TOBELO', $sheetXml);
        $this->assertStringContainsString('DAFTAR PRODUK &amp; STOK BENGKEL', $sheetXml);
        $this->assertStringContainsString('Oli Enduro Racing', $sheetXml);
        $this->assertStringContainsString('45000', $sheetXml);
        $this->assertStringContainsString('58000', $sheetXml);

        $zip->close();
        @unlink($tempPath);
    }

    public function test_transaction_export_returns_valid_xlsx(): void
    {
        $user = User::factory()->create();
        Sale::factory()->create([
            'invoice_number' => 'INV-20261016-0001',
            'grand_total' => 150000,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->get(route('transactions.export', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $content = $response->streamedContent();
        $tempPath = tempnam(sys_get_temp_dir(), 'test_trx_xlsx_');
        file_put_contents($tempPath, $content);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempPath));
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('HISTORI TRANSAKSI KASIR', $sheetXml);
        $this->assertStringContainsString('INV-20261016-0001', $sheetXml);

        $zip->close();
        @unlink($tempPath);
    }

    public function test_default_export_without_format_still_returns_csv_for_backwards_compatibility(): void
    {
        $user = User::factory()->create();
        Product::factory()->withStock(5)->create(['name' => 'Busi Iridium']);

        $response = $this->actingAs($user)->get(route('products.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Busi Iridium', $response->streamedContent());
    }
}
