@props(['status'])
@php
    $colour = match (strtolower(trim((string) $status))) {
        'approved', 'granted', 'active', 'done' => 'bg-[#dcf2e5] text-[#21633b]',
        'lodged', 'in progress' => 'bg-[#dcecf8] text-[#20587c]',
        'awaiting decision', 'pending decision', 'pending' => 'bg-[#fff0cf] text-[#805311]',
        'refused', 'rejected', 'cancelled' => 'bg-red-100 text-red-800',
        default => 'bg-[#e8ebef] text-[#4b5563]',
    };
@endphp
@if(filled($status))
    <span class="inline-block whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold {{ $colour }}">{{ $status }}</span>
@else
    <span class="text-slate-500">&mdash;</span>
@endif
