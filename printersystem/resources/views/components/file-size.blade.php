@props(['bytes'])

@php
    $size = max(0, (float) $bytes);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $unitIndex = 0;

    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    $precision = $unitIndex === 0 || $size >= 10 ? 0 : 1;
@endphp

<span {{ $attributes }}>{{ number_format($size, $precision) }} {{ $units[$unitIndex] }}</span>
