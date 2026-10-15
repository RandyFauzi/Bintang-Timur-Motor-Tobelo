@props(['value', 'payment' => false])
@php
    $labels = $payment
        ? ['paid' => 'Lunas', 'unpaid' => 'Belum Dibayar', 'partial' => 'Dibayar Sebagian']
        : ['completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
    $positive = in_array($value, ['completed', 'paid'], true);
@endphp
<span class="history-status history-status--{{ $value }}">{{ $labels[$value] ?? ucfirst($value) }}</span>
