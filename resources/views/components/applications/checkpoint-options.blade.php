@props(['application', 'checkpoints', 'form' => null])
@foreach($checkpoints as $checkpoint)
    @php
        $saved = $application->checkpoint_statuses[$checkpoint->id] ?? null;
        $selected = old('checkpoint_statuses.'.$checkpoint->id, $saved);
    @endphp
    <div class="application-summary-row my-3">
        <label class="block text-sm font-semibold text-slate-600" for="checkpoint-{{ $checkpoint->id }}">{{ $checkpoint->name }}</label>
        <div>
            <select id="checkpoint-{{ $checkpoint->id }}" name="checkpoint_statuses[{{ $checkpoint->id }}]" @if($form) form="{{ $form }}" @endif class="application-summary-control mt-1 block w-full rounded-md border border-slate-300 bg-white p-2 text-sm">
                <option value="">Select status</option>
                @if($saved !== null && !in_array($saved, $checkpoint->statuses, true))
                    <option value="{{ $saved }}" @selected($selected === $saved)>{{ $saved }} (retired)</option>
                @endif
                @foreach($checkpoint->statuses as $status)
                    <option value="{{ $status }}" @selected($selected === $status)>{{ $status }}</option>
                @endforeach
            </select>
            @error('checkpoint_statuses.'.$checkpoint->id)<p role="alert" class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
        </div>
    </div>
@endforeach
