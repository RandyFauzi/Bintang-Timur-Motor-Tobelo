@props(['product'])
<x-dropdown :portal="true">
    <x-slot:trigger><button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-gray-100 focus-visible:outline focus-visible:outline-2" aria-label="Aksi {{ $product->name }}" :aria-expanded="open"><x-ui.icon name="more-dark" :size="24" /></button></x-slot:trigger>
    <x-slot:content>
        <x-dropdown-link :href="route('returns.index', ['create'=>1, 'product_id'=>$product->id])"><span class="flex items-center gap-2"><x-ui.icon name="return-dark" :size="16" />Retur Produk</span></x-dropdown-link>
        <button type="button" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-700 hover:bg-gray-100 focus:bg-gray-100" @click="$dispatch('delete-product', @js(['name'=>$product->name, 'url'=>route('products.destroy',$product)]))"><x-ui.icon name="trash" :size="16" />Hapus Produk</button>
    </x-slot:content>
</x-dropdown>
