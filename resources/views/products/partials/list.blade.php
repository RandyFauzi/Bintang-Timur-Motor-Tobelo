@php($pageProductIds = $products->modelKeys())
            @if(request('view') === 'cards')
                @if($products->isNotEmpty())
                    <div class="px-3">
                        <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" class="rounded" :checked="pageSelected(@js($pageProductIds))" x-effect="$el.indeterminate = @js($pageProductIds).some(id => has(id)) && !pageSelected(@js($pageProductIds))" @change="togglePage(@js($pageProductIds), $event.target.checked)" aria-label="Pilih semua produk pada halaman ini">
                            <span>Pilih Semua</span>
                        </label>
                    </div>
                @endif
                <div class="catalogue-card-grid">
                    @forelse($products as $product)
                        <article data-product-id="{{ $product->id }}" class="product-card" :class="{ 'is-selected': has({{ $product->id }}) }">
                            <header><div><h2><label class="flex min-h-11 cursor-pointer items-start gap-2"><input type="checkbox" class="mt-1 shrink-0 rounded" :checked="has({{ $product->id }})" @change="toggle({{ $product->id }}, $event.target.checked)" aria-label="Pilih {{ $product->name }}"><span class="min-w-0">{{ $product->name }}</span></label></h2>@if($product->color?->name)<p class="mt-2 text-sm text-muted">{{ $product->color->name }}</p>@endif</div><div class="product-card__actions">
                                <x-ui.product-actions :product="$product" />
                            </div></header>
                            <dl class="product-card__details">
                                <dt>Total Stok</dt><dd><span @class(['stock-badge'=>($product->stock?->quantity ?? 0) <= $product->stock_minimum])>{{ $product->stock?->quantity ?? 0 }}</span></dd>
                                <dt>Tipe Produk</dt><dd>{{ \App\Models\Product::kindLabel($product->product_kind) }}</dd>
                                <dt>Modal</dt><dd>Rp {{ number_format($product->purchase_price,0,',','.') }}</dd>
                                <dt>Harga Jual</dt><dd>Rp {{ number_format($product->selling_price,0,',','.') }}</dd>
                                <dt>Merk Produk</dt><dd><span class="neutral-badge">{{ $product->brand?->name ?? '–' }}</span></dd>
                                <dt>Jenis Part</dt><dd>{{ $product->type?->name ?? '–' }}</dd>
                                <dt>Distributor</dt><dd>{{ $product->distributors->pluck('name')->join(', ') ?: '–' }}</dd>
                            </dl>
                            <a href="{{ request()->fullUrlWithQuery(['edit'=>$product->id,'create'=>null]) }}" class="ui-button ui-button--secondary w-full">Edit Produk <x-ui.icon name="edit" :size="16" /></a>
                        </article>
                    @empty
                        <x-ui.empty-state title="Produk tidak ditemukan" description="Tambahkan produk baru atau ubah pilihan filter." />
                    @endforelse
                </div>
            @else
                <div class="catalogue-table-scroll"><table class="catalogue-table catalogue-table--selectable"><caption class="sr-only">Daftar stok dan produk</caption>
                    <thead><tr><th scope="col"><input type="checkbox" class="rounded" :checked="pageSelected(@js($pageProductIds))" x-effect="$el.indeterminate = @js($pageProductIds).some(id => has(id)) && !pageSelected(@js($pageProductIds))" @change="togglePage(@js($pageProductIds), $event.target.checked)" aria-label="Pilih semua produk pada halaman ini"></th>@foreach(['Info Produk','Modal','Harga Jual','Tipe Produk','Merk','Jenis Part','Distributor','Stok','Aksi'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>@forelse($products as $product)<tr data-product-id="{{ $product->id }}">
                        <td><input type="checkbox" class="rounded" :checked="has({{ $product->id }})" @change="toggle({{ $product->id }}, $event.target.checked)" aria-label="Pilih {{ $product->name }}"></td>
                        <td><span class="font-medium">{{ $product->name }}</span>@if($product->color?->name)<span class="mt-1 block text-muted">{{ $product->color->name }}</span>@endif</td>
                        <td class="whitespace-nowrap">Rp {{ number_format($product->purchase_price,0,',','.') }}</td><td class="whitespace-nowrap">Rp {{ number_format($product->selling_price,0,',','.') }}</td>
                        <td>{{ \App\Models\Product::kindLabel($product->product_kind) }}</td><td><span class="neutral-badge">{{ $product->brand?->name ?? '–' }}</span></td>
                        <td>{{ $product->type?->name ?? '–' }}</td><td>{{ $product->distributors->pluck('name')->join(', ') ?: '–' }}</td>
                        <td><span @class(['stock-badge'=>($product->stock?->quantity ?? 0) <= $product->stock_minimum])>{{ $product->stock?->quantity ?? 0 }}</span></td>
                        <td><div class="flex items-center gap-2"><a href="{{ request()->fullUrlWithQuery(['edit'=>$product->id,'create'=>null]) }}" class="edit-link">Edit</a><x-ui.product-actions :product="$product" /></div></td>
                    </tr>@empty<tr><td colspan="10"><x-ui.empty-state title="Produk tidak ditemukan" description="Tambahkan produk baru atau ubah pilihan filter." /></td></tr>@endforelse</tbody>
                </table></div>
            @endif
            <x-ui.pagination :paginator="$products" :desktop-infinite="true" :show-page-size="false" />
