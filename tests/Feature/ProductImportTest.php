<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_download_template_or_import(): void
    {
        $this->get(route('products.import.template'))->assertRedirect(route('login'));
        $this->post(route('products.import'))->assertRedirect(route('login'));
    }

    public function test_template_import_creates_products_stock_and_supplier_links_without_duplicates(): void
    {
        $user = User::factory()->create();
        $supplier = Distributor::factory()->create(['name' => 'WMS']);
        $this->actingAs($user);
        $file = $this->workbook([$this->row()]);

        $this->post(route('products.import'), ['file' => $file])->assertRedirect(route('products.index'))->assertSessionHasNoErrors();

        $product = Product::query()->sole();
        $this->assertSame('Oli Import', $product->name);
        $this->assertSame('001234567890', $product->barcode);
        $this->assertSame('IMP-001', $product->sku);
        $this->assertSame(7, $product->stock->quantity);
        $this->assertSame('Brembo', $product->brand->name);
        $this->assertSame([$supplier->id], $product->distributors->modelKeys());
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity' => 7, 'created_by' => $user->id]);
        $this->assertDatabaseHas('activity_logs', ['route_name' => 'products.import', 'result' => 'success']);
        $this->post(route('products.import'), ['file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_invalid_row_rolls_back_all_products_and_reports_row_number(): void
    {
        $this->actingAs(User::factory()->create());
        Distributor::factory()->create(['name' => 'WMS']);
        $invalid = $this->row();
        $invalid[9] = 'IMP-002';
        $invalid[8] = '002';
        $invalid[10] = '-1';

        $response = $this->postJson(route('products.import'), ['file' => $this->workbook([$this->row(), $invalid])]);

        $response->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertStringContainsString('Baris 8:', $response->json('errors.file.0'));
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('brands', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_duplicate_sku_within_file_and_unknown_supplier_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $response = $this->postJson(route('products.import'), ['file' => $this->workbook([$this->row(), $this->row()])]);

        $response->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertStringContainsString('duplikat', implode(' ', $response->json('errors.file')));
        $this->assertStringContainsString('Distributor', implode(' ', $response->json('errors.file')));
        $this->assertDatabaseCount('products', 0);
    }

    public function test_formulas_are_rejected_without_evaluating_them(): void
    {
        $this->actingAs(User::factory()->create());
        $file = $this->workbook([$this->row()], true);

        $this->postJson(route('products.import'), ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_empty_template_invalid_archive_and_missing_upload_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson(route('products.import'))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson(route('products.import'), ['file' => UploadedFile::fake()->createWithContent('broken.xlsx', 'broken')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson(route('products.import'), ['file' => $this->workbook([])])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_import_requires_sku_and_limits_the_number_of_products(): void
    {
        $this->actingAs(User::factory()->create());
        $row = $this->row();
        $row[9] = '';
        $this->postJson(route('products.import'), ['file' => $this->workbook([$row])])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson(route('products.import'), ['file' => $this->workbook(array_fill(0, 501, $this->row()))])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_excel_shared_strings_are_imported_and_unsafe_xml_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        Distributor::factory()->create(['name' => 'WMS']);
        $file = $this->workbook([$this->row()], sharedStrings: true);

        $this->post(route('products.import'), ['file' => $file])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['sku' => 'IMP-001', 'barcode' => '001234567890']);
        $path = tempnam(sys_get_temp_dir(), 'unsafe_import_');
        try {
            file_put_contents($path, file_get_contents($file->getRealPath()));
            $zip = new ZipArchive;
            $zip->open($path);
            $zip->addFromString('xl/workbook.xml', '<!DOCTYPE workbook [<!ENTITY unsafe SYSTEM "file:///invalid">]><workbook/>');
            $zip->close();
            $unsafe = UploadedFile::fake()->createWithContent('unsafe.xlsx', file_get_contents($path));
        } finally {
            unlink($path);
        }
        $this->postJson(route('products.import'), ['file' => $unsafe])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_variation_product_type_can_be_imported_from_excel(): void
    {
        $this->actingAs(User::factory()->create());
        Distributor::factory()->create(['name' => 'WMS']);
        $row = $this->row();
        $row[6] = 'Variasi';

        $this->post(route('products.import'), ['file' => $this->workbook([$row])])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['sku' => 'IMP-001', 'product_kind' => 'variation']);
    }

    public function test_import_validation_reopens_import_tab(): void
    {
        $this->actingAs(User::factory()->create());
        $this->followingRedirects()->from(route('products.index', ['create' => 1]))
            ->post(route('products.import'), ['_dialog' => 'product-form', '_tab' => 'import'])
            ->assertOk()->assertSee('Import Excel')->assertSee('Download Template Excel')
            ->assertSee('Pilih file Excel terlebih dahulu.');
    }

    /** @return list<string> */
    private function row(): array
    {
        return ['Oli Import', 'Brembo', 'Oli & Cairan', '', '45000', '58000', 'Harian', 'WMS', '001234567890', 'IMP-001', '7', '2'];
    }

    /** @param list<list<string>> $rows */
    private function workbook(array $rows, bool $formula = false, bool $sharedStrings = false): UploadedFile
    {
        $template = $this->get(route('products.import.template'))->assertDownload('template-import-produk.xlsx')->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'import_test_');
        try {
            file_put_contents($path, $template);
            $zip = new ZipArchive;
            $zip->open($path);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $xml = '';
            $strings = [];
            foreach ($rows as $index => $row) {
                $number = 7 + $index;
                $xml .= '<row r="'.$number.'">';
                foreach ($row as $column => $value) {
                    $reference = chr(65 + $column).$number;
                    if ($sharedStrings) {
                        $strings[] = $value;
                        $xml .= '<c r="'.$reference.'" t="s"><v>'.(count($strings) - 1).'</v></c>';
                    } else {
                        $xml .= '<c r="'.$reference.'" t="inlineStr">'.($formula && $column === 0 ? '<f>1+1</f>' : '').'<is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
                    }
                }
                $xml .= '</row>';
            }
            if ($sharedStrings) {
                $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('', array_map(fn (string $value): string => '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>', $strings)).'</sst>');
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('</sheetData>', $xml.'</sheetData>', $sheet));
            $zip->close();

            return UploadedFile::fake()->createWithContent('products.xlsx', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }
}
