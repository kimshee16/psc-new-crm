@props(['name', 'label', 'options', 'value' => null, 'required' => false])
@php
    $selectedValue = old($name, $value);
    $selectedValue = is_scalar($selectedValue) ? (string) $selectedValue : '';
@endphp
<div class="application-summary-row">
    <label for="summary-{{ $name }}">{{ $label }}@if ($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif</label>
    <div>
        <select id="summary-{{ $name }}" name="{{ $name }}" class="application-summary-control" @required($required)
            @if ($errors->has($name)) aria-invalid="true" aria-describedby="summary-{{ $name }}-error" @endif>
            <option value="">{{ $required ? 'Select…' : 'None' }}</option>
            @foreach ($options as $key => $text)
                <option value="{{ $key }}" @selected($selectedValue === (string) $key)>{{ $text }}</option>
            @endforeach
        </select>
        @error($name)<p id="summary-{{ $name }}-error" class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
