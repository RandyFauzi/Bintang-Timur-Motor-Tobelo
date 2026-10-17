<?php

namespace App\Services;

use App\Http\Requests\ProductRequest;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\ProductImportColumn;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ProductImportService
{
    public const HEADERS = ['Nama Produk', 'Merk', 'Jenis Part', 'Warna', 'Modal', 'Harga Jual', 'Tipe Produk', 'Distributor', 'Barcode', 'SKU', 'Jumlah Stok', 'Stok Minimum', 'ID'];

    public function __construct(private CatalogueService $catalogue) {}

    /** @param array<string, ?string> $mapping */
    public function import(UploadedFile $file, int $userId, array $mapping = []): int
    {
        $rows = $this->read($file, $mapping);
        $request = new ProductRequest;
        $request->setMethod('POST');
        $suppliers = Distributor::where('is_active', true)->get()->keyBy(fn (Distributor $supplier): string => mb_strtolower($supplier->name));
        $validated = [];
        $errors = [];
        $seen = ['id' => [], 'sku' => [], 'barcode' => []];
        foreach ($rows as $number => $data) {
            foreach (['id', 'brand_name', 'type_name', 'color_name', 'product_kind', 'supplier_names', 'sku', 'barcode'] as $field) {
                if (array_key_exists($field, $data) && in_array(mb_strtolower(trim($data[$field] ?? '')), ['', 'null', '-', '–'], true)) {
                    $data[$field] = null;
                }
            }
            $id = $data['id'] ?? null;
            $product = null;
            if ($id !== null) {
                if (! preg_match('/^[1-9][0-9]*$/', $id) || ! ($product = Product::find($id))) {
                    $errors[] = 'Baris '.$number.': ID produk tidak ditemukan.';

                    continue;
                }
            }
            foreach (['id', 'sku', 'barcode'] as $field) {
                $value = mb_strtolower($data[$field] ?? '');
                if ($value !== '' && isset($seen[$field][$value])) {
                    $errors[] = 'Baris '.$number.': '.$field.' duplikat dengan baris '.$seen[$field][$value].'.';
                }
                if ($value !== '') {
                    $seen[$field][$value] = $number;
                }
            }
            unset($data['id']);
            $provided = array_keys($data);
            if (array_key_exists('product_kind', $data)) {
                $data['product_kind'] = match (mb_strtolower($data['product_kind'] ?? $product?->product_kind ?? 'harian')) {
                    'harian', 'produk harian', 'daily' => 'daily',
                    'variasi', 'produk variasi', 'variation' => 'variation',
                    'non harian', 'non-harian', 'produk non harian', 'produk non-harian', 'non_daily' => 'non_daily',
                    default => $data['product_kind'],
                };
            }
            $data += [
                'name' => $product?->name,
                'purchase_price' => $product?->purchase_price ?? 0,
                'selling_price' => $product?->selling_price ?? 0,
                'quantity' => $product?->stock?->quantity ?? 0,
                'stock_minimum' => $product?->stock_minimum ?? 0,
                'product_kind' => $product?->product_kind ?? 'daily',
            ];
            if (array_key_exists('supplier_names', $data)) {
                $data['distributor_ids'] = [];
                foreach (array_filter(array_map('trim', explode('|', $data['supplier_names'] ?? ''))) as $name) {
                    $supplier = $suppliers->get(mb_strtolower($name));
                    if (! $supplier) {
                        $errors[] = 'Baris '.$number.': Distributor "'.$name.'" belum tersedia. Tambahkan distributor terlebih dahulu.';
                    } else {
                        $data['distributor_ids'][] = $supplier->id;
                    }
                }
                $data['distributor_ids'] = array_values(array_unique($data['distributor_ids']));
                $provided[] = 'distributor_ids';
                unset($data['supplier_names']);
            }
            $rules = $request->rules();
            foreach (['sku', 'barcode'] as $field) {
                $rules[$field] = ['nullable', 'string', 'max:100', Rule::unique('products', $field)->ignore($product?->id)];
            }
            $validator = Validator::make($data, $rules, array_merge($request->messages(), ['product_kind.in' => 'Tipe Produk harus Harian, Non-Harian, atau Variasi. Boleh dikosongkan.']));
            foreach ($validator->errors()->all() as $message) {
                $errors[] = 'Baris '.$number.': '.$message;
            }
            if (! $validator->fails()) {
                $attributes = $validator->validated();
                if ($product) {
                    $attributes = array_intersect_key($attributes, array_flip($provided));
                }
                $validated[] = ['id' => $product?->id, 'data' => $attributes, 'row' => $number];
            }
            if (count($errors) >= 50) {
                break;
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => array_slice($errors, 0, 50)]);
        }
        try {
            DB::transaction(function () use ($validated, $userId): void {
                foreach ($validated as $row) {
                    $product = $row['id'] ? Product::lockForUpdate()->find($row['id']) : null;
                    if ($row['id'] && ! $product) {
                        $this->invalid('Baris '.$row['row'].': Produk sudah dihapus. Import dibatalkan.');
                    }
                    try {
                        $this->catalogue->saveProduct($row['data'], $userId, $product, true);
                    } catch (ValidationException $exception) {
                        $this->invalid('Baris '.$row['row'].': '.implode(' ', $exception->validator->errors()->all()));
                    }
                }
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                $this->invalid('SKU atau barcode sudah digunakan. Tidak ada baris yang disimpan. Periksa file dan coba kembali.');
            }
            throw $exception;
        }

        return count($validated);
    }

    /** @param array<string, ?string> $mapping
     * @return array<int, array<string, ?string>>
     */
    private function read(UploadedFile $file, array $mapping): array
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            $this->invalid('File Excel tidak dapat dibaca. Gunakan template .xlsx yang tersedia.');
        }
        try {
            if ($zip->numFiles > 200) {
                $this->invalid('File Excel terlalu kompleks. Gunakan template yang tersedia.');
            }
            $size = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $size += $zip->statIndex($index)['size'];
                if ($size > 20 * 1024 * 1024) {
                    $this->invalid('Isi file Excel terlalu besar setelah dibuka.');
                }
            }
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $first = $workbook->query('//*[local-name()="sheet"]')->item(0);
            if (! $first instanceof DOMElement) {
                $this->invalid('Sheet produk tidak ditemukan.');
            }
            $relationId = $first->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            $relations = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $sheetPath = null;
            foreach ($relations->query('//*[local-name()="Relationship"]') as $relation) {
                if ($relation->getAttribute('Id') === $relationId && $relation->getAttribute('TargetMode') !== 'External') {
                    $sheetPath = ltrim($relation->getAttribute('Target'), '/');
                    $sheetPath = str_starts_with($sheetPath, 'xl/') ? $sheetPath : 'xl/'.$sheetPath;
                }
            }
            if (! $sheetPath || ! preg_match('~^xl/worksheets/[^/]+\.xml$~', $sheetPath)) {
                $this->invalid('Sheet pertama harus berisi data produk sesuai template.');
            }
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $shared = $this->xml($zip, 'xl/sharedStrings.xml');
                foreach ($shared->query('//*[local-name()="si"]') as $item) {
                    $strings[] = $this->text($shared, $item);
                }
            }
            $sheet = $this->xml($zip, $sheetPath);
            $rows = [];
            $columns = [];
            $aliases = [];
            foreach (ProductImportColumn::definitions() as $field => $definition) {
                $names = ! empty($mapping[$field]) ? [$mapping[$field]] : [$field, $definition['label'], ...$definition['aliases']];
                foreach ($names as $name) {
                    $key = $this->normalizeHeader($name);
                    if (isset($aliases[$key]) && $aliases[$key] !== $field) {
                        $this->invalid('Pemetaan kolom ambigu: '.$name.'. Gunakan judul yang berbeda.');
                    }
                    $aliases[$key] = $field;
                }
            }
            $lastRow = 0;
            foreach ($sheet->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $number = (int) $row->getAttribute('r');
                if ($number <= $lastRow) {
                    $this->invalid('Nomor baris Excel tidak valid.');
                }
                $lastRow = $number;
                $values = [];
                foreach ($sheet->query('./*[local-name()="c"]', $row) as $cell) {
                    if ($sheet->query('./*[local-name()="f"]', $cell)->length > 0) {
                        $this->invalid('Baris '.$number.': Rumus tidak didukung. Ganti rumus dengan nilainya.');
                    }
                    if (! preg_match('/^([A-Z]+)'.$number.'$/', $cell->getAttribute('r'), $match)) {
                        $this->invalid('Baris '.$number.': Referensi sel tidak valid.');
                    }
                    $value = match ($cell->getAttribute('t')) {
                        'inlineStr' => $this->text($sheet, $cell),
                        's' => $this->sharedString($sheet, $cell, $strings),
                        '', 'n', 'str' => $sheet->evaluate('string(./*[local-name()="v"])', $cell),
                        default => $this->invalid('Baris '.$number.': Tipe sel tidak didukung.'),
                    };
                    $value = trim($value ?? '');
                    if (mb_strlen($value) > 2000) {
                        $this->invalid('Baris '.$number.': Isi sel terlalu panjang.');
                    }
                    $values[$match[1]] = $value === '' ? null : $value;
                }
                if ($columns === []) {
                    $candidate = [];
                    foreach ($values as $column => $value) {
                        $field = $aliases[$this->normalizeHeader($value ?? '')] ?? null;
                        if ($field !== null) {
                            if (in_array($field, $candidate, true)) {
                                $this->invalid('Judul kolom duplikat: '.$value.'.');
                            }
                            $candidate[$column] = $field;
                        }
                    }
                    if (in_array('id', $candidate, true) || in_array('name', $candidate, true)) {
                        foreach ($mapping as $field => $header) {
                            if (! empty($header) && ! in_array($field, $candidate, true)) {
                                $this->invalid('Kolom "'.$header.'" tidak ditemukan pada judul file. Periksa pemetaan kolom.');
                            }
                        }
                        $columns = $candidate;
                    }

                    continue;
                }
                $data = [];
                foreach ($columns as $column => $field) {
                    $data[$field] = $values[$column] ?? null;
                }
                if (array_filter($data, fn (?string $value): bool => $value !== null) !== []) {
                    $rows[$number] = $data;
                }
            }
            if ($columns === [] || $rows === []) {
                $this->invalid('File belum berisi produk. Pastikan judul kolom ID atau Nama Produk dikenali, atau atur pemetaan kolom.');
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function normalizeHeader(string $value): string
    {
        return preg_replace('/[^\pL\pN]/u', '', mb_strtolower(trim($value)));
    }

    private function xml(ZipArchive $zip, string $path): DOMXPath
    {
        $xml = $zip->getFromName($path);
        if ($xml === false || str_contains($xml, "\0") || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            $this->invalid('Struktur file Excel tidak didukung. Gunakan template yang tersedia.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                $this->invalid('File Excel rusak atau tidak valid.');
            }

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function text(DOMXPath $xml, \DOMNode $node): string
    {
        $text = '';
        foreach ($xml->query('.//*[local-name()="t"]', $node) as $part) {
            $text .= $part->textContent;
        }

        return $text;
    }

    /** @param list<string> $strings */
    private function sharedString(DOMXPath $xml, DOMElement $cell, array $strings): string
    {
        $index = $xml->evaluate('string(./*[local-name()="v"])', $cell);
        if (! preg_match('/^\d+$/', $index) || ! array_key_exists((int) $index, $strings)) {
            $this->invalid('Referensi teks di file Excel rusak. Simpan ulang sesuai template.');
        }

        return $strings[(int) $index];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
