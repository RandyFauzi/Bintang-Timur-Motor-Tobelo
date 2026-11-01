        <div class="overflow-x-auto" role="region" aria-label="Tabel tagihan distributor, geser untuk melihat seluruh kolom" tabindex="0"><table class="invoice-table text-left text-sm"><thead class="border-y bg-gray-50 text-muted"><tr><th class="invoice-table__select"><input type="checkbox" class="rounded" :checked="allSelected" x-effect="$el.indeterminate = selected.length > 0 && !allSelected" @change="toggleAll($event.target.checked)" aria-label="Pilih semua invoice yang dimuat, maksimal 100"></th>@foreach(['Invoice / PO','Distributor','Tanggal Faktur / Masuk','Jatuh Tempo','Total Tagihan','Dibayarkan','Sisa','Status','Retur / Selisih'] as $label)<th class="whitespace-nowrap font-medium">{{ $label }}</th>@endforeach<th class="whitespace-nowrap font-medium">Aksi</th></tr></thead><tbody>
        @forelse($invoices as $invoice)<tr class="border-b hover:bg-gray-50" data-invoice-id="{{ $invoice->id }}" data-invoice-version="{{ $invoice->version }}" :class="{ 'invoice-row-selected': has({{ $invoice->id }}) }"><td class="invoice-table__select"><input type="checkbox" class="rounded" :checked="has({{ $invoice->id }})" @change="toggle({{ $invoice->id }}, {{ $invoice->version }}, $event.target.checked); $el.checked = has({{ $invoice->id }})" aria-label="Pilih invoice {{ $invoice->reference }}"></td>
            <td class="p-4">
                <a class="whitespace-nowrap font-medium underline underline-offset-4" href="{{ route('distributor-invoices.show',$invoice) }}">{{ $invoice->reference }}</a>
                @if($invoice->purchaseOrder)
                    <p class="mt-1 text-xs text-muted">{{ $invoice->purchaseOrder->reference }}</p>
                @else
                    <span class="mt-1 inline-block text-[11px] font-medium text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">Manual / Tanpa PO</span>
                @endif
                @if($invoice->supplier_reference)
                    <p class="mt-1 text-xs text-muted">Nota: {{ $invoice->supplier_reference }}</p>
                @endif
            </td>
            <td class="p-4 font-medium">{{ $invoice->distributor_name }}</td>
            <td class="whitespace-nowrap p-4">
                <p>Faktur: {{ $invoice->invoiced_on?->format('d/m/Y') ?? 'Belum diisi' }}</p>
                <p class="mt-1 text-xs text-muted">Masuk: {{ $invoice->receivedAt()?->format('d/m/Y') ?? ($invoice->purchaseOrder ? 'Menunggu approval kargo' : '-') }}</p>
                @if($invoice->shipment)
                    <a class="mt-1 inline-block text-xs underline" href="{{ route('shipments.index',['shipment'=>$invoice->shipment_id]) }}#shipment-detail">{{ $invoice->shipment->reference }}</a>
                @endif
            </td>
            <td class="whitespace-nowrap p-4">
                {{ $invoice->due_on->format('d/m/Y') }}
                <p class="mt-1 text-xs text-muted">{{ $invoice->timingLabel() }}</p>
            </td>
            <td class="whitespace-nowrap p-4">Rp {{ number_format($invoice->total_cents/100,2,',','.') }}</td>
            <td class="whitespace-nowrap p-4">Rp {{ number_format($invoice->paid_cents/100,2,',','.') }}</td>
            <td class="whitespace-nowrap p-4 font-medium">Rp {{ number_format($invoice->remainingCents()/100,2,',','.') }}</td>
            <td class="p-4">
                <x-ui.invoice-status :status="$invoice->billingStatus()" />
                @if($invoice->billingStatus() === 'overdue')
                    <p class="mt-2 text-xs text-muted">{{ \App\Models\DistributorInvoice::STATUSES[$invoice->status()] }}</p>
                @endif
            </td>
            <td class="invoice-table__notes">
                <p class="break-words">{{ \Illuminate\Support\Str::limit($invoice->discrepancy_notes ?: '-',100) }}</p>
                @if($invoice->claim_amount_cents > 0)
                    <p class="mt-2 text-xs text-muted">Klaim dicatat: Rp {{ number_format($invoice->claim_amount_cents/100,2,',','.') }}</p>
                @endif
            </td>
            <td class="invoice-table__actions whitespace-nowrap">
                <div class="inline-flex items-center gap-2 whitespace-nowrap">
                    @if($invoice->remainingCents() > 0)
                        <a class="ui-button whitespace-nowrap inline-flex items-center gap-1 !min-h-8 !py-1 !px-2.5 !text-xs !bg-emerald-700 hover:!bg-emerald-800 !text-white font-medium shadow-sm" href="{{ route('distributor-invoices.show',['distributorInvoice'=>$invoice->id,'pay'=>1]) }}">
                            <x-ui.icon name="wallet" :size="13" class="brightness-0 invert" />Bayar
                        </a>
                    @endif
                    <a class="ui-button ui-button--secondary whitespace-nowrap inline-flex items-center gap-1 !min-h-8 !py-1 !px-2.5 !text-xs"
                        href="{{ route('distributor-invoices.show', ['distributorInvoice' => $invoice->id, 'edit' => 1]) }}"
                        title="Edit Data Tagihan"
                        @click.prevent="$dispatch('open-edit-invoice', {{ json_encode([
                            'id' => $invoice->id,
                            'updateUrl' => route('distributor-invoices.update', $invoice),
                            'version' => $invoice->version,
                            'isManual' => $invoice->isManual(),
                            'distributor_id' => (string) ($invoice->distributor_id ?? ''),
                            'distributor_name' => $invoice->distributor_name,
                            'reference' => $invoice->reference,
                            'invoiced_on' => $invoice->invoiced_on?->toDateString() ?? '',
                            'due_on' => $invoice->due_on->toDateString(),
                            'total' => number_format($invoice->total_cents / 100, 2, '.', ''),
                            'min_total' => number_format($invoice->paid_cents / 100, 2, '.', ''),
                            'supplier_reference' => $invoice->supplier_reference ?? '',
                            'shipment_id' => (string) ($invoice->shipment_id ?? ''),
                            'notes' => $invoice->notes ?? '',
                            'discrepancy_notes' => $invoice->discrepancy_notes ?? '',
                            'claim_amount' => number_format($invoice->claim_amount_cents / 100, 2, '.', ''),
                            'note_issued' => (bool) ($invoice->purchaseOrder?->note_issued),
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }})">
                        <x-ui.icon name="edit" :size="13" />Edit Data
                    </a>
                    <a class="ui-button ui-button--secondary whitespace-nowrap inline-flex items-center !min-h-8 !py-1 !px-2.5 !text-xs" href="{{ route('distributor-invoices.show',$invoice) }}">Detail</a>
                    <button type="button" class="ui-button ui-button--secondary whitespace-nowrap inline-flex items-center justify-center !min-h-8 !h-8 !w-8 !p-0 !text-xs text-red-600 hover:text-red-700 hover:bg-red-50 hover:border-red-300"
                        title="Hapus Tagihan"
                        aria-label="Hapus tagihan {{ $invoice->reference }}"
                        @click="$dispatch('open-delete-invoice', {{ json_encode([
                            'id' => $invoice->id,
                            'reference' => $invoice->reference,
                            'distributor' => $invoice->distributor_name,
                            'po' => $invoice->purchaseOrder?->reference ?? 'Manual',
                            'version' => $invoice->version,
                            'hasPayments' => $invoice->paid_cents > 0,
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }})">
                        <x-ui.icon name="trash" :size="14" />
                    </button>
                </div>
            </td>
        </tr>
        @empty<tr><td colspan="11"><x-ui.empty-state title="Belum ada tagihan yang ditemukan" description="Tagihan otomatis muncul setelah PO dibuat, atau tambahkan tagihan manual / import file Excel." /></td></tr>@endforelse
        </tbody></table></div><x-ui.pagination :paginator="$invoices" :desktop-infinite="true" :show-page-size="false" />
