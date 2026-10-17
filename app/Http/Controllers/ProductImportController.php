<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductImportRequest;
use App\Services\ExcelExportService;
use App\Services\ProductImportService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportController extends Controller
{
    public function template(ExcelExportService $excel): StreamedResponse
    {
        return $excel->download('template-import-produk.xlsx', 'TEMPLATE IMPORT PRODUK', ProductImportService::HEADERS, [], 'Isi baris di bawah judul kolom. ID terisi memperbarui produk; ID kosong menambah produk.');
    }

    public function store(ProductImportRequest $request, ProductImportService $importer): RedirectResponse
    {
        $count = $importer->import($request->file('file'), $request->user()->id, $request->validated('mapping', []));

        return redirect()->route('products.index')->with('success', $count.' baris produk berhasil ditambahkan atau diperbarui.');
    }
}
