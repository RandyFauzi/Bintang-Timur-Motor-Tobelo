            <div class="notification-list">
                @forelse($notifications as $notification)
                    @php($urgent = (int) $notification->priority <= 1)
                    <article @class(['notification-item','notification-item--urgent'=>$urgent])>
                        <span class="notification-item__icon" aria-hidden="true"><x-ui.icon :name="$notification->source === 'product' ? 'stock-box' : ($notification->source === 'invoice' ? 'wallet' : 'ship')" :size="24" /></span>
                        <div class="notification-item__body">
                            <div class="notification-item__labels"><span class="notification-kind">{{ \App\Services\NotificationService::TYPES[$notification->category] }}</span>@if($urgent)<span class="notification-priority">Perlu tindakan</span>@endif</div>
                            <h2>{{ $notification->title }}</h2>
                            @if($notification->source === 'product')
                                <p>{{ $notification->category === 'out_of_stock' ? 'Stok produk sudah habis.' : 'Stok produk mencapai batas minimum.' }} Sisa <strong>{{ (int) $notification->quantity }}</strong> unit · Stok minimum {{ (int) $notification->minimum }} unit.</p>
                                <small>SKU: {{ $notification->reference }} · Perbarui stok setelah barang tersedia.</small>
                            @elseif($notification->source === 'invoice')
                                <p>Sisa tagihan <strong>Rp {{ number_format($notification->quantity/100,2,',','.') }}</strong> � {{ $notification->context }}</p>
                                <small>{{ $notification->reference }} � Jatuh tempo {{ \Carbon\CarbonImmutable::parse($notification->due_on)->format('d/m/Y') }} (WIT)</small>
                                <p class="notification-item__hint">{{ $notification->category === 'invoice_overdue' ? 'Pembayaran melewati jatuh tempo. Periksa dan catat pembayaran ke distributor.' : 'Tagihan jatuh tempo dalam 7 hari atau kurang. Siapkan pembayaran distributor.' }}</p>
                            @else
                                <p>{{ $notification->context }} · {{ $notification->origin }} → {{ $notification->destination }}</p>
                                <small>Resi {{ $notification->reference }} · Estimasi tiba {{ \Carbon\CarbonImmutable::parse($notification->due_on)->locale('id')->translatedFormat('j M Y') }} (WIT)</small>
                                <p class="notification-item__hint">{{ $notification->category === 'shipment_delayed' ? 'Estimasi tiba terlewati. Hubungi ekspedisi atau konfirmasi jika barang sudah diterima.' : 'Cargo diperkirakan tiba dalam 2 hari atau kurang. Siapkan penerimaan barang.' }}</p>
                            @endif
                        </div>
                        <a href="{{ $notification->source === 'product' ? route('products.index',['edit'=>$notification->source_id]) : ($notification->source === 'invoice' ? route('distributor-invoices.show',$notification->source_id) : route('shipments.index',['shipment'=>$notification->source_id]).'#shipment-detail') }}" class="ui-button ui-button--secondary notification-item__action">{{ $notification->source === 'product' ? 'Perbarui Stok' : ($notification->source === 'invoice' ? 'Lihat Tagihan' : 'Lihat Pengiriman') }}<span class="sr-only">: {{ $notification->title }}</span><span aria-hidden="true">→</span></a>
                    </article>
                @empty
                    <x-ui.empty-state title="Tidak ada notifikasi" description="Semua kondisi sudah aman, atau tidak ada hasil yang sesuai dengan filter Anda." />
                @endforelse
            </div>
            <x-ui.pagination :paginator="$notifications" :always-show-navigation="true" />
