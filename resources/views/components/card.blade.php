@props(['variant' => 'default', 'class' => ''])

@php
$classes = match($variant) {
    'ghost' => 'bg-gray-50 border border-gray-200',
    default => 'bg-white border border-gray-200 shadow-sm',
};
@endphp

<div {{ $attributes->merge(['class' => $classes . ' rounded-lg p-6 ' . $class]) }}>
    {{ $slot }}
</div>