<x-app-layout>
    <div class="catalogue-page">
        <header class="catalogue-heading"><h1>Distributor</h1></header>
        <div class="catalogue-summary catalogue-summary--equal">
            <div class="catalogue-summary__metric"><span class="text-sm text-muted">Jumlah Distributor</span><strong>{{ number_format($distributorCount,0,',','.') }} Distributor</strong><span class="text-xs text-muted">Distributor aktif saat ini</span><x-ui.icon name="stock-box" /></div>
            <div class="catalogue-summary__metric"><span class="text-sm text-muted">Produk Terhubung</span><strong>{{ number_format($linkedProductCount,0,',','.') }} Produk</strong><span class="text-xs text-muted">Produk dengan mitra distributor</span><x-ui.icon name="stock-box" /></div>
        </div>
        <section class="catalogue-surface" aria-label="Daftar distributor">
            <form method="GET" action="{{ route('distributors.index') }}">
                <x-ui.catalogue-toolbar export-route="distributors.export" create-label="Tambah Distributor" dialog="distributor-form" />
                <div class="catalogue-filters"><x-ui.multiselect name="type_ids" label="Semua Jenis Part" :options="$types" /><label class="filter-check"><input type="checkbox" name="has_products" value="1" @checked(request()->boolean('has_products')) onchange="this.form.requestSubmit()"> Dengan produk terhubung</label><a href="{{ route('distributors.index',['view'=>request('view','list')]) }}" class="filter-reset">Reset filter</a></div>
            </form>
            <div @class(['distributor-list','catalogue-card-grid'=>request('view') === 'cards'])>
                @forelse($distributors as $distributor)
                    <article class="distributor-card">
                        <header><h2>{{ $distributor->name }}</h2><a href="{{ request()->fullUrlWithQuery(['edit'=>$distributor->id,'create'=>null]) }}" aria-label="Edit {{ $distributor->name }}"><x-ui.icon name="edit" :size="18" /></a></header>
                        <div class="distributor-card__content">
                            <div class="category-legend">@forelse($categoryCounts[$distributor->id] ?? [] as $category)<span class="category-chip"><i style="--category-color: var(--chart-{{ ($types->pluck('name')->search($category->name) ?: 0) % 6 }})"></i>{{ $category->name }} <span>{{ $category->value }}</span></span>@empty<span class="text-xs text-muted">Belum ada produk terhubung.</span>@endforelse</div>
                            <a href="{{ route('products.index',['distributor_ids'=>[$distributor->id]]) }}" class="distributor-total"><strong>{{ $distributor->products_count }}</strong> Total Produk</a>
                        </div>
                        @if($distributor->phone || $distributor->city)<p class="mt-3 text-xs text-muted">{{ collect([$distributor->phone,$distributor->city])->filter()->join(' · ') }}</p>@endif
                    </article>
                @empty<x-ui.empty-state title="Distributor tidak ditemukan" description="Tambahkan distributor atau ubah pilihan filter." />@endforelse
            </div>
            <x-ui.pagination :paginator="$distributors" />
        </section>
        <x-ui.dialog name="distributor-form" :title="$editing ? 'Edit Distributor' : 'Tambah Distributor'" description="Kelola informasi mitra pemasok produk." :show="$editing !== null || request()->boolean('create') || old('_dialog') === 'distributor-form'">
            <form method="POST" action="{{ $editing ? route('distributors.update',$editing) : route('distributors.store') }}" class="dialog-form" @submit="submitting = true">
                @csrf @if($editing) @method('PATCH') @endif<input type="hidden" name="_dialog" value="distributor-form">
                <div class="form-grid">
                    <x-ui.field name="name" label="Nama Distributor" :value="$editing?->name" required maxlength="255" />
                    <x-ui.field name="company_name" label="Nama Perusahaan" :value="$editing?->company_name" maxlength="255" />
                    <x-ui.field name="phone" label="Telepon" :value="$editing?->phone" type="tel" maxlength="50" />
                    <x-ui.field name="email" label="Email" :value="$editing?->email" type="email" maxlength="255" />
                    <x-ui.field name="city" label="Kota" :value="$editing?->city" maxlength="255" />
                    <x-ui.field name="contact_person" label="Kontak Penanggung Jawab" :value="$editing?->contact_person" maxlength="255" />
                    <div class="form-grid__full"><x-ui.field name="address" label="Alamat" :value="$editing?->address" maxlength="2000" /></div>
                    <div class="form-grid__full"><x-ui.field name="notes" label="Catatan" :value="$editing?->notes" maxlength="2000" /></div>
                </div>
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Menyimpan…' : 'Simpan'">Simpan</button></footer>
            </form>
        </x-ui.dialog>
    </div>
</x-app-layout>
