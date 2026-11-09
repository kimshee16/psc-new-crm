@props(['application', 'name', 'label', 'type' => 'text', 'required' => false])
@php
    $value = $application->$name;
    if ($value instanceof \DateTimeInterface) $value = $value->format('Y-m-d');
    $value = old($name, $value);
    $value = is_scalar($value) ? $value : '';
@endphp
<div class="application-summary-row">
    <label for="nomination-{{ $name }}">{{ $label }}@if($required)<span class="text-red-600"> *</span>@endif</label>
    <div>
        <input id="nomination-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" @required($required)
            {{ $attributes->class(['application-summary-control']) }}
            @if($type === 'text') maxlength="255" @endif
            @if($errors->has($name)) aria-invalid="true" aria-describedby="nomination-{{ $name }}-error" @endif>
        @if($type === 'date')<span class="mt-1 block text-xs text-slate-500">dd/mm/yyyy</span>@endif
        @error($name)<p id="nomination-{{ $name }}-error" class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
