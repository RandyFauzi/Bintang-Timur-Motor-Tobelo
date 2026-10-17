<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\ProductImportColumn;
use App\Models\User;
use App\Services\ExcelExportService;
use Database\Seeders\ProductImportColumnSeeder;
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

    public function test_duplicate_sku_within_file_is_rejected_without_creating_distributors(): void
    {
        $this->actingAs(User::factory()->create());
        $response = $this->postJson(route('products.import'), ['file' => $this->workbook([$this->row(), $this->row()])]);

        $response->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertStringContainsString('duplikat', implode(' ', $response->json('errors.file')));
        $this->assertDatabaseCount('distributors', 0);
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

    public function test_more_than_500_rows_can_be_imported_with_automatic_skus(): void
    {
        $this->actingAs(User::factory()->create());
        $rows = [];
        for ($index = 0; $index < 501; $index++) {
            $rows[] = ['Produk '.$index];
        }
        $this->post(route('products.import'), ['file' => $this->workbook($rows, headers: ['Nama Produk'])])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 501);
        $this->assertSame(501, Product::where('sku', 'like', 'PRD-%')->count());
    }

    public function test_matching_id_replaces_values_and_records_only_stock_difference(): void
    {
        $this->actingAs(User::factory()->create());
        Distributor::factory()->create(['name' => 'WMS']);
        $this->post(route('products.import'), ['file' => $this->workbook([$this->row()])])->assertSessionHasNoErrors();
        $product = Product::sole();
        $row = $this->row();
        $row[0] = 'Nama Baru';
        $row[9] = 'SKU-BARU';
        $row[10] = '10';
        $row[] = (string) $product->id;
        $file = $this->workbook([$row]);
        $this->post(route('products.import'), ['file' => $file])->assertSessionHasNoErrors();
        $this->post(route('products.import'), ['file' => $file])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Nama Baru', 'sku' => 'SKU-BARU']);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity' => 3, 'type' => 'adjustment']);
        $this->assertSame(10, $product->fresh()->stock->quantity);
    }

    public function test_dynamic_database_aliases_and_custom_mapping_preserve_missing_fields(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->withStock(12)->create(['selling_price' => 15000, 'stock_minimum' => 4]);
        ProductImportColumn::factory()->create(['field' => 'id', 'label' => 'Nomor Internal', 'aliases' => ['kode internal']]);
        $this->seed(ProductImportColumnSeeder::class);
        $this->assertDatabaseCount('product_import_columns', 13);
        $file = $this->workbook([['abaikan', 'Nama Diperbarui', (string) $product->id]], headers: ['Catatan', 'Judul Barang', 'Kode Internal']);
        $this->post(route('products.import'), ['file' => $file, 'mapping' => ['name' => 'Judul Barang']])->assertSessionHasNoErrors();
        $this->assertSame('Nama Diperbarui', $product->fresh()->name);
        $this->assertEquals(15000, $product->fresh()->selling_price);
        $this->assertSame(12, $product->fresh()->stock->quantity);
        $this->assertSame(4, $product->fresh()->stock_minimum);
    }

    public function test_invalid_or_duplicate_ids_and_reserved_stock_roll_back_import(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->withStock(10)->create();
        $product->stock->update(['reserved_quantity' => 5]);
        foreach ([
            [[(string) $product->id, '4']],
            [['99999999', '10']],
            [[(string) $product->id, '10'], [(string) $product->id, '11']],
        ] as $rows) {
            $this->postJson(route('products.import'), ['file' => $this->workbook($rows, headers: ['ID', 'Stok'])])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
            $this->assertSame(10, $product->fresh()->stock->quantity);
            $this->assertDatabaseCount('stock_movements', 0);
        }
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

    public function test_export_can_be_reimported_by_id_without_duplicating_products(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->withStock(9)->create(['barcode' => '000123', 'stock_minimum' => 3]);
        $supplier = Distributor::factory()->create();
        $product->distributors()->attach($supplier);
        $content = $this->get(route('products.export', ['format' => 'xlsx']))->assertOk()->streamedContent();
        $file = UploadedFile::fake()->createWithContent('export.xlsx', $content);
        $this->post(route('products.import'), ['file' => $file])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('000123', $product->fresh()->barcode);
        $this->assertSame(9, $product->fresh()->stock->quantity);
        $this->assertSame([$supplier->id], $product->fresh()->distributors->modelKeys());
    }

    public function test_ambiguous_or_missing_custom_headers_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([['name' => 'ID'], ['id' => 'Nomor tidak ada']] as $mapping) {
            $this->postJson(route('products.import'), ['file' => $this->workbook([$this->row()]), 'mapping' => $mapping])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_optional_import_fields_accept_blank_null_and_placeholder_values(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['', 'NULL', '-', '–'] as $index => $empty) {
            $row = $this->row();
            foreach ([1, 2, 3, 6, 7, 8, 9] as $column) {
                $row[$column] = $empty;
            }
            $row[0] = 'Produk Kosong '.$index;
            $this->post(route('products.import'), ['file' => $this->workbook([$row])])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('products', 4);
        $this->assertSame(4, Product::where('product_kind', 'daily')->whereNull('barcode')->whereNull('brand_id')->count());
        $this->assertDatabaseCount('product_distributors', 0);
    }

    public function test_unknown_product_kind_has_a_clear_message_and_blank_update_preserves_existing_kind(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->withStock()->create(['product_kind' => 'variation']);
        $file = $this->workbook([[(string) $product->id, 'NULL']], headers: ['ID', 'Tipe Produk']);
        $this->post(route('products.import'), ['file' => $file])->assertSessionHasNoErrors();
        $this->assertSame('variation', $product->fresh()->product_kind);
        $invalid = $this->workbook([[(string) $product->id, 'Asal']], headers: ['ID', 'Tipe Produk']);
        $this->postJson(route('products.import'), ['file' => $invalid])->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 7: Tipe Produk harus Harian, Non-Harian, atau Variasi. Boleh dikosongkan.');
    }

    public function test_unknown_distributors_are_created_once_and_existing_names_are_reused(): void
    {
        $this->actingAs(User::factory()->create());
        $existing = Distributor::factory()->create(['name' => 'WMS']);
        $first = $this->row();
        $first[7] = ' wms | Baru | baru | - ';
        $second = $this->row();
        $second[8] = 'BARCODE-2';
        $second[9] = 'SKU-2';
        $second[7] = 'BARU';
        $this->post(route('products.import'), ['file' => $this->workbook([$first, $second])])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('distributors', 2);
        $new = Distributor::where('name', 'Baru')->sole();
        $this->assertSame([$existing->id, $new->id], Product::where('sku', 'IMP-001')->sole()->distributors->modelKeys());
        $this->assertSame([$new->id], Product::where('sku', 'SKU-2')->sole()->distributors->modelKeys());
    }

    public function test_new_distributors_are_rolled_back_when_stock_update_fails(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->withStock(10)->create();
        $product->stock->update(['reserved_quantity' => 5]);
        $file = $this->workbook([[(string) $product->id, '4', 'Distributor Baru']], headers: ['ID', 'Stok', 'Distributor']);
        $this->postJson(route('products.import'), ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('distributors', 0);
        $this->assertSame(10, $product->fresh()->stock->quantity);
    }

    public function test_inactive_or_oversized_distributor_names_are_not_imported(): void
    {
        $this->actingAs(User::factory()->create());
        Distributor::factory()->create(['name' => 'WMS', 'is_active' => false]);
        foreach (['WMS', str_repeat('A', 256)] as $name) {
            $row = $this->row();
            $row[7] = $name;
            $this->postJson(route('products.import'), ['file' => $this->workbook([$row])])->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('distributors', 1);
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
    private function workbook(array $rows, bool $formula = false, bool $sharedStrings = false, ?array $headers = null): UploadedFile
    {
        $template = $headers === null
            ? $this->get(route('products.import.template'))->assertDownload('template-import-produk.xlsx')->streamedContent()
            : app(ExcelExportService::class)->download('custom.xlsx', 'DATA PRODUK', $headers, [], '')->getCallback();
        if ($headers !== null) {
            ob_start();
            $template();
            $template = ob_get_clean();
        }
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
