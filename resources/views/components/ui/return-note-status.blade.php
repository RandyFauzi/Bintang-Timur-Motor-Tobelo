@props(['value'])
<span @class(['return-note-status', 'return-note-status--credited' => $value === 'credited'])>{{ $value === 'credited' ? 'Sudah Dikurangi' : 'Belum Dikurangi' }}{{ $slot }}</span>
