<?php

namespace App\Services;

use Illuminate\Support\Str;

class ActivityChangePresenter
{
    public const ENTITY_NAMES = [
        'Sale' => 'Penjualan (Kasir)',
        'SaleItem' => 'Item Penjualan',
        'SaleCorrection' => 'Koreksi Penjualan',
        'Product' => 'Produk',
        'ProductStock' => 'Stok Produk',
        'StockMovement' => 'Riwayat / Mutasi Stok',
        'Distributor' => 'Distributor',
        'DistributorInvoice' => 'Tagihan Distributor',
        'DistributorPayment' => 'Pembayaran Tagihan',
        'PurchaseOrder' => 'Pesanan Pengadaan (PO)',
        'PurchaseOrderItem' => 'Item Pengadaan (PO)',
        'PurchasePlan' => 'Rencana Pengadaan',
        'Shipment' => 'Kargo Pengiriman',
        'ShippingService' => 'Ekspedisi / Layanan Kargo',
        'Expense' => 'Pengeluaran Toko',
        'User' => 'Akun Pengguna',
        'Brand' => 'Merk Produk',
        'ProductType' => 'Jenis Part',
        'Color' => 'Warna',
        'ProductReturn' => 'Retur Produk',
        'ProductReturnEvent' => 'Riwayat Retur',
        'CompetitorObservation' => 'Riset Kompetitor',
        'Workshop' => 'Bengkel Rekanan',
        'WorkType' => 'Jenis Pekerjaan',
        'BusinessSetting' => 'Pengaturan Usaha',
    ];

    public const FIELD_LABELS = [
        'name' => 'Nama',
        'invoice_number' => 'Nomor Faktur / Nota',
        'reference' => 'Nomor Referensi',
        'supplier_reference' => 'Nomor Nota Distributor',
        'product_name' => 'Nama Produk',
        'product_id' => 'ID Produk',
        'sku' => 'Kode SKU',
        'barcode' => 'Barcode',
        'quantity' => 'Jumlah',
        'stock_minimum' => 'Stok Minimum',
        'purchase_price' => 'Harga Beli (Modal)',
        'selling_price' => 'Harga Jual',
        'subtotal' => 'Subtotal',
        'grand_total' => 'Total Transaksi',
        'discount' => 'Diskon',
        'tax' => 'Pajak',
        'paid_amount' => 'Jumlah Dibayar',
        'change_amount' => 'Kembalian',
        'payment_status' => 'Status Pembayaran',
        'status' => 'Status',
        'product_kind' => 'Tipe Produk',
        'distributor_name' => 'Nama Distributor',
        'distributor_id' => 'ID Distributor',
        'total_cents' => 'Total Tagihan',
        'paid_cents' => 'Jumlah Dibayar',
        'claim_amount_cents' => 'Nilai Klaim',
        'due_on' => 'Jatuh Tempo',
        'received_on' => 'Tanggal Masuk',
        'invoiced_on' => 'Tanggal Faktur',
        'ordered_on' => 'Tanggal Pemesanan',
        'amount' => 'Nominal',
        'expense_date' => 'Tanggal Pengeluaran',
        'category' => 'Kategori',
        'description' => 'Keterangan',
        'notes' => 'Catatan',
        'discrepancy_notes' => 'Catatan Selisih / Retur',
        'customer_name' => 'Nama Pelanggan',
        'customer_phone' => 'Telepon Pelanggan',
        'role' => 'Peran Akun',
        'email' => 'Alamat Email',
        'phone' => 'Nomor Telepon',
        'cargo_name' => 'Nama Kargo / Kapal',
        'awb' => 'Nomor Resi / AWB',
        'status_cargo' => 'Status Kargo',
        'delivered_at' => 'Waktu Tiba',
        'is_active' => 'Status Aktif',
        'is_registered' => 'Terdaftar',
        'unit' => 'Satuan',
    ];

    /**
     * Format a single change entry into human-friendly data.
     *
     * @param  array{entity: string, id: string|int, event: string, before?: array<string, mixed>, after?: array<string, mixed>}  $change
     * @return array{
     *     entityName: string,
     *     entityTitle: string,
     *     rawEntity: string,
     *     rawId: string,
     *     event: string,
     *     eventLabel: string,
     *     eventColor: string,
     *     modifiedFields: list<array{field: string, label: string, before: string, after: string}>,
     *     summaryFields: list<array{field: string, label: string, value: string}>
     * }
     */
    public function present(array $change): array
    {
        $rawEntity = $change['entity'] ?? 'Data';
        $rawId = (string) ($change['id'] ?? '');
        $event = $change['event'] ?? 'updated';
        $before = (array) ($change['before'] ?? []);
        $after = (array) ($change['after'] ?? []);

        $entityName = self::ENTITY_NAMES[$rawEntity] ?? Str::headline($rawEntity);
        $entityTitle = $this->determineEntityTitle($rawEntity, $rawId, $before, $after);

        [$eventLabel, $eventColor] = match ($event) {
            'created' => ['Data Baru Dibuat', 'emerald'],
            'deleted' => ['Data Dihapus', 'rose'],
            default => ['Data Diubah', 'blue'],
        };

        $modifiedFields = [];
        $summaryFields = [];

        if ($event === 'updated') {
            // Find fields that actually changed
            $allKeys = array_unique(array_merge(array_keys($before), array_keys($after)));
            foreach ($allKeys as $key) {
                if ($this->shouldIgnoreKey($key)) {
                    continue;
                }
                $beforeVal = $before[$key] ?? null;
                $afterVal = $after[$key] ?? null;

                $modifiedFields[] = [
                    'field' => $key,
                    'label' => self::FIELD_LABELS[$key] ?? Str::headline($key),
                    'before' => $this->formatValue($key, $beforeVal),
                    'after' => $this->formatValue($key, $afterVal),
                ];
            }
        } else {
            // For created or deleted, show significant attributes
            $source = $event === 'created' ? $after : $before;
            foreach ($source as $key => $val) {
                if ($this->shouldIgnoreKey($key)) {
                    continue;
                }
                $summaryFields[] = [
                    'field' => $key,
                    'label' => self::FIELD_LABELS[$key] ?? Str::headline($key),
                    'value' => $this->formatValue($key, $val),
                ];
            }
        }

        return [
            'entityName' => $entityName,
            'entityTitle' => $entityTitle,
            'rawEntity' => $rawEntity,
            'rawId' => $rawId,
            'event' => $event,
            'eventLabel' => $eventLabel,
            'eventColor' => $eventColor,
            'modifiedFields' => $modifiedFields,
            'summaryFields' => $summaryFields,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @return list<array<string, mixed>>
     */
    public function presentAll(array $changes): array
    {
        return array_map(fn (array $change): array => $this->present($change), $changes);
    }

    private function determineEntityTitle(string $entity, string $id, array $before, array $after): string
    {
        $pool = array_merge($before, $after);

        if (! empty($pool['name'])) {
            return (string) $pool['name'];
        }
        if (! empty($pool['product_name'])) {
            return (string) $pool['product_name'];
        }
        if (! empty($pool['invoice_number'])) {
            return 'Nota: '.(string) $pool['invoice_number'];
        }
        if (! empty($pool['reference'])) {
            return 'Ref: '.(string) $pool['reference'];
        }
        if (! empty($pool['category'])) {
            return 'Kategori: '.(string) $pool['category'];
        }
        if (! empty($pool['title'])) {
            return (string) $pool['title'];
        }

        return '#'.Str::limit($id, 12, '…');
    }

    private function shouldIgnoreKey(string $key): bool
    {
        return in_array($key, [
            'id', 'created_at', 'updated_at', 'deleted_at', 'remember_token',
            'version', 'password', 'token',
        ], true);
    }

    public function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '– (kosong)';
        }

        if ($value === '[DISAMARKAN]') {
            return '🔒 [Disamarkan]';
        }

        if (is_bool($value)) {
            return $value ? 'Ya / Aktif' : 'Tidak / Nonaktif';
        }

        if (str_ends_with($field, '_cents')) {
            $rupiah = (float) $value / 100;

            return 'Rp '.number_format($rupiah, 0, ',', '.');
        }

        if (in_array($field, ['selling_price', 'purchase_price', 'subtotal', 'grand_total', 'amount', 'paid_amount', 'change_amount', 'discount', 'tax'], true) && is_numeric($value)) {
            return 'Rp '.number_format((float) $value, 0, ',', '.');
        }

        if ($field === 'status' || $field === 'payment_status') {
            return match ((string) $value) {
                'paid' => 'Lunas',
                'unpaid' => 'Belum Dibayar',
                'completed' => 'Selesai',
                'pending' => 'Menunggu',
                'cancelled' => 'Dibatalkan',
                'active' => 'Aktif',
                default => Str::headline((string) $value),
            };
        }

        if ($field === 'product_kind') {
            return match ((string) $value) {
                'daily' => 'Produk Harian',
                'non_daily' => 'Produk Non Harian',
                'variation' => 'Produk Variasi',
                'body' => 'Produk Body',
                'printilan' => 'Produk Printilan',
                default => Str::headline((string) $value),
            };
        }

        if ($field === 'role') {
            return match ((string) $value) {
                'admin' => 'Administrator',
                'staff' => 'Staff Kasir',
                default => Str::headline((string) $value),
            };
        }

        if ($field === 'is_active') {
            return $value ? 'Aktif' : 'Nonaktif';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
