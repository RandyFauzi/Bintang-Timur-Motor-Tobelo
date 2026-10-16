<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
        $this->assertStringContainsString('Bintang Timur', $sheetXml);
        $this->assertStringContainsString('DAFTAR PRODUK &amp; STOK BENGKEL', $sheetXml);
        $this->assertStringContainsString('Oli Enduro Racing', $sheetXml);
        $this->assertStringContainsString('45000', $sheetXml);
        $this->assertStringContainsString('58000', $sheetXml);

        $zip->close();
        @unlink($tempPath);
    }

    public function test_export_embeds_the_configured_business_logo_and_escapes_its_name(): void
    {
        Storage::fake('local');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6eZ8AAAAASUVORK5CYII=');
        Storage::disk('local')->put('branding/logos/test.png', $image);
        BusinessSetting::factory()->create(['business_name' => 'Toko & Motor', 'logo_path' => 'branding/logos/test.png']);
        $response = $this->actingAs(User::factory()->create())->get(route('products.export', ['format' => 'xlsx']));
        $path = tempnam(sys_get_temp_dir(), 'branded_export_');
        try {
            file_put_contents($path, $response->streamedContent());
            $zip = new ZipArchive;
            $zip->open($path);
            $this->assertSame($image, $zip->getFromName('xl/media/business-logo.png'));
            $this->assertStringContainsString('Toko &amp; Motor', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $this->assertStringContainsString('rIdBrand', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $this->assertStringContainsString('../media/business-logo.png', $zip->getFromName('xl/drawings/_rels/drawing1.xml.rels'));
            $this->assertStringContainsString('rIdLogo', $zip->getFromName('xl/drawings/drawing1.xml'));
            $zip->close();
        } finally {
            unlink($path);
        }
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
