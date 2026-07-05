@props(['type' => 'info'])

@php
    $alertType = in_array($type, ['info', 'success', 'warning', 'danger'], true) ? $type : 'info';
@endphp

<div {{ $attributes->merge(['class' => 'alert alert--' . $alertType]) }} role="status">
    {{ $slot }}
</div>
