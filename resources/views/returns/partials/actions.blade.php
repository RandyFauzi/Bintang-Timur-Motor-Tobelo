<details class="return-actions">
    <summary aria-label="Aksi {{ $record->product_snapshot['name'] }}"><x-ui.icon name="more" :size="18" /></summary>
    <nav aria-label="Aksi retur">
        @if($record->status === 'active')
            <a href="{{ request()->fullUrlWithQuery(['edit'=>$record->id, 'mode'=>'cancel', 'create'=>null]) }}"><x-ui.icon name="undo" :size="18" />Batalkan Retur</a>
            <a href="{{ request()->fullUrlWithQuery(['edit'=>$record->id, 'mode'=>'delete', 'create'=>null]) }}" class="text-danger"><x-ui.icon name="trash" :size="18" />Hapus Data Retur</a>
        @endif
        <a href="{{ request()->fullUrlWithQuery(['edit'=>$record->id, 'mode'=>'activity', 'create'=>null]) }}">Log Aktivitas</a>
    </nav>
</details>
