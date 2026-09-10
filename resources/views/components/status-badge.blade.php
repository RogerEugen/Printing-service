@props(['status'])
@php
    $statusEnum = $status instanceof \App\Enums\PrintJobStatus ? $status : \App\Enums\PrintJobStatus::from($status);
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold '.$statusEnum->badgeClasses()]) }}>{{ $statusEnum->label() }}</span>
