@props(['value', 'payment' => false])
@php
    $labels = $payment
        ? ['paid' => 'Lunas', 'unpaid' => 'Belum Dibayar', 'partial' => 'Dibayar Sebagian']
        : ['completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
    $positive = in_array($value, ['completed', 'paid'], true);
@endphp
<span @class(['neutral-badge', 'text-success' => $positive, 'text-danger' => $value === 'cancelled'])>{{ $labels[$value] ?? ucfirst($value) }}</span>
