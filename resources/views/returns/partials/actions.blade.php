<x-dropdown :portal="true">
    <x-slot:trigger>
        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg hover:bg-gray-100 focus-visible:outline focus-visible:outline-2" aria-label="Aksi {{ $record->product_snapshot['name'] }}" :aria-expanded="open">
            <x-ui.icon name="more-dark" :size="24" />
        </button>
    </x-slot:trigger>
    <x-slot:content>
        @if($record->status === 'active')
            <x-dropdown-link :href="request()->fullUrlWithQuery(['edit'=>$record->id, 'mode'=>'cancel', 'create'=>null])"><span class="flex items-center gap-2"><x-ui.icon name="return-dark" :size="18" />Batalkan Retur</span></x-dropdown-link>
            <x-dropdown-link :href="request()->fullUrlWithQuery(['edit'=>$record->id, 'mode'=>'delete', 'create'=>null])"><span class="flex items-center gap-2 text-red-700"><x-ui.icon name="trash" :size="18" />Hapus Data Retur</span></x-dropdown-link>
        @endif
        <x-dropdown-link :href="request()->fullUrlWithQuery(['edit'=>$record->id, 'mode'=>'activity', 'create'=>null])">Log Aktivitas</x-dropdown-link>
    </x-slot:content>
</x-dropdown>
