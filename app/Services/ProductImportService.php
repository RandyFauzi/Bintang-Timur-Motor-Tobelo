<?php

namespace App\Services;

use App\Http\Requests\ProductRequest;
use App\Models\Distributor;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ProductImportService
{
    public const HEADERS = ['Nama Produk', 'Merk', 'Jenis Part', 'Warna', 'Modal', 'Harga Jual', 'Tipe Produk', 'Distributor', 'Barcode', 'SKU', 'Jumlah Stok', 'Stok Minimum'];

    public function __construct(private CatalogueService $catalogue) {}

    public function import(UploadedFile $file, int $userId): int
    {
        $rows = $this->read($file);
        $request = new ProductRequest;
        $request->setMethod('POST');
        $rules = $request->rules();
        $rules['sku'] = array_merge(['required'], $rules['sku']);
        $suppliers = Distributor::where('is_active', true)->get()->keyBy(fn (Distributor $supplier): string => mb_strtolower($supplier->name));
        $validated = [];
        $errors = [];
        $seen = ['sku' => [], 'barcode' => []];
        foreach ($rows as $number => $row) {
            $data = array_combine(['name', 'brand_name', 'type_name', 'color_name', 'purchase_price', 'selling_price', 'product_kind', 'supplier_names', 'barcode', 'sku', 'quantity', 'stock_minimum'], $row);
            $data['product_kind'] = match (mb_strtolower($data['product_kind'] ?? 'harian')) {
                'harian', 'daily' => 'daily',
                'non harian', 'non-harian', 'non_daily' => 'non_daily',
                default => $data['product_kind'],
            };
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
            unset($data['supplier_names']);
            $validator = Validator::make($data, $rules, $request->messages());
            foreach ($validator->errors()->all() as $message) {
                $errors[] = 'Baris '.$number.': '.$message;
            }
            foreach (['sku', 'barcode'] as $field) {
                $value = mb_strtolower($data[$field] ?? '');
                if ($value !== '' && isset($seen[$field][$value])) {
                    $errors[] = 'Baris '.$number.': '.$field.' duplikat dengan baris '.$seen[$field][$value].'.';
                }
                if ($value !== '') {
                    $seen[$field][$value] = $number;
                }
            }
            if (! $validator->fails()) {
                $validated[] = $validator->validated();
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
                foreach ($validated as $data) {
                    $this->catalogue->saveProduct($data, $userId);
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

    /** @return array<int, list<?string>> */
    private function read(UploadedFile $file): array
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
            $headerFound = false;
            $lastRow = 0;
            foreach ($sheet->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $number = (int) $row->getAttribute('r');
                if ($number <= $lastRow || $number > 10000) {
                    $this->invalid('Nomor baris Excel tidak valid.');
                }
                $lastRow = $number;
                $values = array_fill(0, count(self::HEADERS), null);
                foreach ($sheet->query('./*[local-name()="c"]', $row) as $cell) {
                    if ($sheet->query('./*[local-name()="f"]', $cell)->length > 0) {
                        $this->invalid('Baris '.$number.': Rumus tidak didukung. Ganti rumus dengan nilainya.');
                    }
                    if (! preg_match('/^([A-L])'.$number.'$/', $cell->getAttribute('r'), $match)) {
                        if (trim($cell->textContent) !== '') {
                            $this->invalid('Baris '.$number.': Kolom di luar template tidak didukung.');
                        }

                        continue;
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
                    $values[ord($match[1]) - 65] = $value === '' ? null : $value;
                }
                if (! $headerFound) {
                    if ($values === self::HEADERS) {
                        $headerFound = true;
                    } elseif ($number > 10) {
                        $this->invalid('Judul kolom tidak sesuai template. Download template dan isi ulang.');
                    }

                    continue;
                }
                if (array_filter($values, fn (?string $value): bool => $value !== null) === []) {
                    continue;
                }
                $rows[$number] = $values;
                if (count($rows) > 500) {
                    $this->invalid('Maksimal 500 produk per import.');
                }
            }
            if (! $headerFound || $rows === []) {
                $this->invalid('File belum berisi produk atau judul kolom tidak sesuai template.');
            }

            return $rows;
        } finally {
            $zip->close();
        }
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
