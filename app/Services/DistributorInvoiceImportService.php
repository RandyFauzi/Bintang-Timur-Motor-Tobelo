<?php

namespace App\Services;

use App\Models\Distributor;
use App\Models\DistributorInvoice;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class DistributorInvoiceImportService
{
    private bool $date1904 = false;

    public const HEADERS = [
        'Nomor Invoice',
        'Distributor',
        'Tanggal Faktur',
        'Jatuh Tempo',
        'Total Tagihan',
        'Nomor Nota',
        'Catatan',
        'Tanggal Masuk Barang',
    ];

    public const EXAMPLE_ROWS = [
        ['INV-202610-001', 'PT Astra Otoparts', '2026-10-25', '2026-11-25', 15000000, 'FAK-88912', 'Tagihan suku cadang bulan Oktober', '2026-10-27'],
        ['', 'CV Makmur Sentosa', '2026-10-28', '2026-12-28', 7500000, 'SJ-4410', 'Ban & velg motor', ''],
    ];

    /** @var array<string, list<string>> */
    private const COLUMN_ALIASES = [
        'reference' => ['nomorinvoice', 'noinvoice', 'invoice', 'nomortagihan', 'notagihan', 'ref'],
        'distributor' => ['distributor', 'namadistributor', 'supplier', 'pemasok'],
        'invoiced_on' => ['tanggalfaktur', 'tglfaktur', 'tanggaltagihan', 'tanggalnota', 'tanggal'],
        'received_on' => ['tanggalmasukbarang', 'tanggalmasuk', 'tglmasuk', 'tanggalditerima', 'tanggalbarangditerima', 'receivedon'],
        'due_on' => ['jatuhtempo', 'tgljatuhtempo', 'tanggaljatuhtempo', 'tempo', 'duedate'],
        'total' => ['totaltagihan', 'totalfaktur', 'total', 'nominal', 'jumlah', 'jumlahbruto'],
        'supplier_reference' => ['nomornota', 'nonota', 'nota', 'suratjalan', 'nosuratjalan'],
        'notes' => ['catatan', 'keterangan', 'notes'],
    ];

    public function __construct(private DistributorInvoiceService $invoiceService) {}

    public function import(UploadedFile $file): int
    {
        if (! Schema::hasColumn('distributor_invoices', 'received_on')) {
            $this->invalid('Impor belum dapat diproses karena database server belum diperbarui. Minta administrator menjalankan php artisan migrate --force, lalu unggah ulang file. Belum ada data yang disimpan.');
        }

        $rows = $this->read($file);
        if (empty($rows)) {
            $this->invalid('File Excel kosong atau tidak memiliki baris data yang valid.');
        }

        $distributors = Distributor::all()->keyBy(fn (Distributor $d): string => mb_strtolower(trim($d->name)));
        $existingReferences = DistributorInvoice::pluck('reference')->map(fn (string $r): string => mb_strtolower($r))->flip()->all();

        $errors = [];
        $validatedRows = [];
        $seenReferencesInFile = [];

        foreach ($rows as $rowNumber => $data) {
            $distributorName = trim($data['distributor'] ?? '');
            if ($distributorName === '') {
                $errors[] = "Baris {$rowNumber}: Nama distributor wajib diisi.";

                continue;
            }

            foreach (['distributor' => 255, 'reference' => 255, 'supplier_reference' => 255, 'notes' => 2000] as $field => $limit) {
                if (mb_strlen($data[$field] ?? '') > $limit) {
                    $errors[] = "Baris {$rowNumber}: Kolom {$field} maksimal {$limit} karakter.";
                }
            }

            // Total
            $rawTotal = $data['total'] ?? '';
            $totalCents = $this->parseMoney($rawTotal);
            if ($totalCents === null || $totalCents <= 0) {
                $errors[] = "Baris {$rowNumber}: Total tagihan tidak valid (harus lebih dari 0).";
            }

            // Invoiced on
            $rawInvoicedOn = trim($data['invoiced_on'] ?? '');
            $invoicedOn = null;
            if ($rawInvoicedOn !== '') {
                $invoicedOn = $this->parseDate($rawInvoicedOn);
                if (! $invoicedOn) {
                    $errors[] = "Baris {$rowNumber}: Format tanggal faktur '{$rawInvoicedOn}' tidak valid (gunakan YYYY-MM-DD atau DD/MM/YYYY).";
                }
            } else {
                $invoicedOn = DistributorInvoice::today()->toDateString();
            }

            $rawReceivedOn = trim($data['received_on'] ?? '');
            $receivedOn = $rawReceivedOn !== '' ? $this->parseDate($rawReceivedOn) : null;
            if ($rawReceivedOn !== '' && $receivedOn === null) {
                $errors[] = "Baris {$rowNumber}: Format tanggal masuk barang '{$rawReceivedOn}' tidak valid (gunakan YYYY-MM-DD atau DD/MM/YYYY).";
            }

            // Due on
            $rawDueOn = trim($data['due_on'] ?? '');
            $dueOn = null;
            if ($rawDueOn === '') {
                $errors[] = "Baris {$rowNumber}: Tanggal jatuh tempo wajib diisi.";
            } else {
                $dueOn = $this->parseDate($rawDueOn);
                if (! $dueOn) {
                    $errors[] = "Baris {$rowNumber}: Format tanggal jatuh tempo '{$rawDueOn}' tidak valid (gunakan YYYY-MM-DD atau DD/MM/YYYY).";
                } elseif ($invoicedOn && $dueOn < $invoicedOn) {
                    $errors[] = "Baris {$rowNumber}: Jatuh tempo ({$dueOn}) tidak boleh sebelum tanggal faktur ({$invoicedOn}).";
                }
            }

            // Reference
            $ref = trim($data['reference'] ?? '');
            if ($ref !== '') {
                $refLower = mb_strtolower($ref);
                if (isset($existingReferences[$refLower])) {
                    $errors[] = "Baris {$rowNumber}: Nomor invoice '{$ref}' sudah terdaftar dalam sistem.";
                } elseif (isset($seenReferencesInFile[$refLower])) {
                    $errors[] = "Baris {$rowNumber}: Nomor invoice '{$ref}' duplikat dengan baris {$seenReferencesInFile[$refLower]}.";
                } else {
                    $seenReferencesInFile[$refLower] = $rowNumber;
                }
            }

            $validatedRows[] = [
                'row_number' => $rowNumber,
                'distributor_name' => $distributorName,
                'reference' => $ref !== '' ? $ref : null,
                'invoiced_on' => $invoicedOn,
                'received_on' => $receivedOn,
                'due_on' => $dueOn,
                'total_cents' => $totalCents,
                'supplier_reference' => trim($data['supplier_reference'] ?? '') ?: null,
                'notes' => trim($data['notes'] ?? '') ?: null,
            ];
        }

        if (! empty($errors)) {
            $this->invalid(implode("\n", array_slice($errors, 0, 10)).(count($errors) > 10 ? "\n...dan ".(count($errors) - 10).' kesalahan lainnya.' : ''));
        }

        return DB::transaction(function () use ($validatedRows, &$distributors): int {
            $reservedReferences = array_values(array_filter(array_column($validatedRows, 'reference')));
            $count = 0;
            foreach ($validatedRows as $row) {
                $distNameLower = mb_strtolower($row['distributor_name']);
                if (! isset($distributors[$distNameLower])) {
                    $distributor = Distributor::create(['name' => $row['distributor_name']]);
                    $distributors[$distNameLower] = $distributor;
                } else {
                    $distributor = $distributors[$distNameLower];
                }

                $reference = $row['reference'] ?? $this->invoiceService->generateManualReference($reservedReferences);

                DistributorInvoice::create([
                    'distributor_id' => $distributor->id,
                    'purchase_order_id' => null,
                    'reference' => $reference,
                    'invoiced_on' => $row['invoiced_on'],
                    'received_on' => $row['received_on'],
                    'due_on' => $row['due_on'],
                    'total_cents' => $row['total_cents'],
                    'paid_cents' => 0,
                    'version' => 1,
                    'supplier_reference' => $row['supplier_reference'],
                    'notes' => $row['notes'],
                ]);

                $count++;
            }

            return $count;
        });
    }

    /** @return array<int, array<string, ?string>> */
    private function read(UploadedFile $file): array
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            $this->invalid('File Excel tidak dapat dibaca. Gunakan format .xlsx yang valid.');
        }

        try {
            if ($zip->numFiles > 200) {
                $this->invalid('File Excel terlalu kompleks. Gunakan template yang disediakan.');
            }
            $expandedSize = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $expandedSize += $zip->statIndex($index)['size'];
                if ($expandedSize > 20 * 1024 * 1024) {
                    $this->invalid('Isi file Excel terlalu besar setelah dibuka. Pisahkan menjadi beberapa file.');
                }
            }

            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $this->date1904 = in_array($workbook->evaluate('string(//*[local-name()="workbookPr"]/@date1904)'), ['1', 'true'], true);
            $first = $workbook->query('//*[local-name()="sheet"]')->item(0);
            if (! $first instanceof DOMElement) {
                $this->invalid('Sheet data tidak ditemukan.');
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
                $this->invalid('Sheet pertama harus berisi data tagihan sesuai template.');
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

            foreach (self::COLUMN_ALIASES as $field => $names) {
                foreach ($names as $name) {
                    $aliases[$this->normalizeHeader($name)] = $field;
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
                    if (! preg_match('/^([A-Z]+)'.$number.'$/', $cell->getAttribute('r'), $match)) {
                        continue;
                    }
                    if ($columns !== [] && isset($columns[$match[1]]) &&
                        ($cell->getAttribute('t') === 'e' ||
                        ($sheet->query('./*[local-name()="f"]', $cell)->length > 0 && $sheet->evaluate('string(./*[local-name()="v"])', $cell) === ''))) {
                        $this->invalid("Baris {$number}, sel {$match[1]}{$number}: sel berisi error atau rumus tanpa hasil. Perbaiki rumus atau tempel sebagai nilai sebelum mengunggah.");
                    }
                    $value = match ($cell->getAttribute('t')) {
                        'inlineStr' => $this->text($sheet, $cell),
                        's' => $this->sharedString($sheet, $cell, $strings),
                        '', 'n', 'str' => $sheet->evaluate('string(./*[local-name()="v"])', $cell),
                        default => '',
                    };
                    $value = trim($value ?? '');
                    $values[$match[1]] = $value === '' ? null : $value;
                }

                if ($columns === []) {
                    $candidate = [];
                    foreach ($values as $colLetter => $val) {
                        $field = $aliases[$this->normalizeHeader($val ?? '')] ?? null;
                        if ($field !== null) {
                            $candidate[$colLetter] = $field;
                        }
                    }
                    if (in_array('distributor', $candidate, true) && in_array('due_on', $candidate, true)) {
                        if (! in_array('total', $candidate, true)) {
                            $this->invalid("Baris {$number}: kolom Total Tagihan tidak ditemukan. Gunakan template resmi.");
                        }
                        if (count($candidate) !== count(array_unique($candidate))) {
                            $this->invalid("Baris {$number}: judul kolom duplikat. Gunakan satu kolom untuk setiap jenis data.");
                        }
                        $columns = $candidate;
                    }

                    continue;
                }

                $data = [];
                foreach ($columns as $colLetter => $field) {
                    $data[$field] = $values[$colLetter] ?? null;
                }

                if (array_filter($data, fn (?string $v): bool => $v !== null && $v !== '') !== []) {
                    $rows[$number] = $data;
                }
            }

            if ($columns === [] || $rows === []) {
                $this->invalid('File belum berisi data tagihan atau judul kolom tidak sesuai template. Pastikan kolom Distributor, Jatuh Tempo, dan Total Tagihan tersedia.');
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

    private function parseDate(string $val): ?string
    {
        $val = trim($val);
        if ($val === '') {
            return null;
        }

        if (preg_match('/^\d{1,7}(?:\.\d+)?$/', $val)) {
            $serial = (int) floor((float) $val);
            $maximum = $this->date1904 ? 2957003 : 2958465;
            if ($serial < ($this->date1904 ? 0 : 1) || $serial > $maximum || (! $this->date1904 && $serial === 60)) {
                return null;
            }
            $base = $this->date1904 ? '1904-01-01' : ($serial < 60 ? '1899-12-31' : '1899-12-30');

            return CarbonImmutable::parse($base, 'UTC')->addDays($serial)->toDateString();
        }

        // Standard string dates
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $val);
                if ($parsed && $parsed->format($format) === $val) {
                    return $parsed->toDateString();
                }
            } catch (\Throwable) {
                // Try next format
            }
        }

        return null;
    }

    private function parseMoney(string|float|int|null $val): ?int
    {
        if ($val === null) {
            return null;
        }

        $amount = preg_replace('/^Rp\.?\s*/i', '', trim((string) $val));
        if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/', $amount)) {
            $amount = str_replace(',', '.', str_replace('.', '', $amount));
        } elseif (preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/', $amount)) {
            $amount = str_replace(',', '', $amount);
        } elseif (preg_match('/^\d+,\d{1,2}$/', $amount)) {
            $amount = str_replace(',', '.', $amount);
        }

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            return null;
        }
        $parts = explode('.', $amount, 2);
        $whole = ltrim($parts[0], '0');
        if (strlen($whole) > 14) {
            return null;
        }
        $cents = (int) $whole * 100 + (int) str_pad($parts[1] ?? '', 2, '0');

        return $cents <= 9000000000000000 ? $cents : null;
    }

    private function xml(ZipArchive $zip, string $path): DOMXPath
    {
        $xml = $zip->getFromName($path);
        if ($xml === false || str_contains($xml, "\0") || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            $this->invalid('Struktur file Excel tidak didukung. Gunakan template yang disediakan.');
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
        throw ValidationException::withMessages(['file' => explode("\n", $message)]);
    }
}
