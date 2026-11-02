@props(['from' => '', 'to' => ''])
@php
    $fromLabel = $from ? \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') : '';
    $toLabel = $to ? \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') : '';
    $rangeLabel = $from && $to ? $fromLabel.' – '.$toLabel : ($from ? 'From '.$fromLabel : ($to ? 'Until '.$toLabel : ''));
@endphp
<div class="critical-date-range" data-critical-date-range>
    <label class="sr-only" for="critical-date-range">Critical date range</label>
    <input id="critical-date-range" type="text" readonly value="{{ $rangeLabel }}" placeholder="Select date range"
        class="application-filter" aria-describedby="critical-date-range-help" aria-haspopup="dialog" aria-expanded="false" autocomplete="off" data-range-input>
    <span class="critical-range-calendar" aria-hidden="true">{!! payroll_icon('calendar', 'h-4 w-4') !!}</span>
    <button type="button" class="critical-range-clear" aria-label="Clear critical date range" title="Clear date range" data-range-clear @if (! $from && ! $to) hidden @endif>
        {!! payroll_icon('x', 'h-3.5 w-3.5') !!}
    </button>
    <input type="hidden" name="date_from" value="{{ $from }}" data-range-from>
    <input type="hidden" name="date_to" value="{{ $to }}" data-range-to>
    <span id="critical-date-range-help" class="sr-only">Select a start date, then an end date. Both dates are inclusive. Select the same day twice for a single day.</span>
</div>
