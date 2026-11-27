@php
    $serviceRows = $application->services()->orderBy('id')->get();
    $serviceTypes = \App\Models\ApplicationService::TYPES;
    $money = fn ($cents) => 'A$ '.number_format($cents / 100, 2);
@endphp

<div class="mt-4 space-y-3" id="application-service-list">
    @forelse ($serviceRows as $service)
        @php
            $details = $service->details;
        @endphp
        <article class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-slate-200 px-5 py-4">
            <div class="min-w-0">
                <h3 class="font-bold">{{ $serviceTypes[$service->type] }}{{ $service->type === 'visa' && $details['name'] !== '' ? ' · '.$details['name'] : '' }}</h3>
                @if ($service->type === 'oshc')
                    <p class="text-sm text-slate-600">{{ $details['provider'] }} · {{ $details['cover'] }} · {{ \Illuminate\Support\Carbon::parse($details['starts'])->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($details['ends'])->format('d/m/Y') }}</p>
                    @if ($service->screenshot)
                        <a class="text-sm text-[#1f7890] underline" href="{{ route('applications.services.screenshot', [$application, $service]) }}">Download screenshot</a>
                    @endif
                @elseif ($service->type === 'visa')
                    <p class="text-sm text-slate-600">{{ collect($details['quantities'])->filter()->map(fn ($quantity, $key) => \App\Models\ApplicationService::VISA_FEES[$key].' × '.$quantity)->implode(' · ') }}</p>
                @elseif (isset($details['quantity']))
                    <p class="text-sm text-slate-600">{{ $details['quantity'] }}{{ $service->type === 'translation' ? ' page(s)' : '' }} × {{ $money($details['rate']) }}</p>
                @elseif (isset($details['description']))
                    <p class="whitespace-pre-wrap break-words text-sm text-slate-600">{{ $details['description'] }}</p>
                @endif
            </div>
            <div class="flex items-center gap-5">
                <strong class="tabular-nums">{{ $money($service->total_cents) }}</strong>
                @if ($canEditServices)
                    <button type="button" data-edit-service="{{ $service->id }}" aria-label="Edit {{ $serviceTypes[$service->type] }}" class="text-sm font-semibold text-[#1f7890] underline">Edit</button>
                @endif
            </div>
        </article>
    @empty
        <p class="rounded-lg border border-dashed border-slate-300 p-5 text-sm text-slate-600">No services added yet.</p>
    @endforelse
    <div class="flex flex-wrap justify-between gap-3 rounded-lg bg-slate-100 px-5 py-4 font-bold">
        <span>Services subtotal (AUD)</span>
        <span class="tabular-nums text-[#1f7890]">{{ $money($serviceRows->sum('total_cents')) }}</span>
    </div>
</div>

@if ($canEditServices)
    @php
        $inputClass = 'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm';
        $buttonClass = 'rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold';
        $rowsForEdit = $serviceRows->mapWithKeys(fn ($service) => [$service->id => [
            'type' => $service->type,
            'details' => $service->details,
            'screenshot' => (bool) $service->screenshot,
            'url' => route('applications.services.update', [$application, $service]),
        ]]);
    @endphp
    <dialog id="application-service-dialog" aria-labelledby="application-service-heading" class="m-auto max-h-[90vh] w-[min(680px,95vw)] overflow-y-auto rounded-xl bg-white p-6 text-slate-800 shadow-xl backdrop:bg-slate-900/50">
        <div class="flex items-center justify-between gap-4">
            <h2 id="application-service-heading" class="text-xl font-bold">Add a service</h2>
            <button type="button" data-service-close class="rounded px-2 text-2xl" aria-label="Close">&times;</button>
        </div>
        <form class="mt-5 space-y-4">
            @csrf
            <p role="alert" hidden class="whitespace-pre-line rounded-md bg-red-50 p-3 text-sm text-red-800"></p>
            <label class="block text-sm font-semibold">Service
                <select name="type" required class="{{ $inputClass }}">
                    <option value="">Select a service</option>
                    @foreach ($serviceTypes as $type => $label)
                        <option value="{{ $type }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <fieldset data-service-types="oshc" hidden class="space-y-4">
                @foreach (['provider' => ['Provider', \App\Models\ApplicationService::PROVIDERS], 'cover' => ['Cover type', \App\Models\ApplicationService::COVERS]] as $name => [$label, $options])
                    <label class="block text-sm font-semibold">{{ $label }}
                        <select name="{{ $name }}" required class="{{ $inputClass }}">
                            <option value="">Select {{ strtolower($label) }}</option>
                            @foreach ($options as $option)
                                <option>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-semibold">Policy start date
                        <input type="date" name="starts" required class="{{ $inputClass }}">
                    </label>
                    <label class="text-sm font-semibold">Policy end date
                        <input type="date" name="ends" required class="{{ $inputClass }}">
                    </label>
                </div>
            </fieldset>
            <fieldset data-service-types="other" hidden>
                <label class="block text-sm font-semibold">Description
                    <textarea name="description" rows="3" maxlength="2000" required class="{{ $inputClass }}"></textarea>
                </label>
            </fieldset>
            <fieldset data-service-types="oshc agent other" hidden>
                <label class="block text-sm font-semibold"><span data-amount-label>Amount (AUD)</span>
                    <input type="number" name="amount" step="0.01" min="0" max="999999999.99" required class="{{ $inputClass }}">
                </label>
            </fieldset>
            <fieldset data-service-types="oshc" hidden class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                <label class="block text-sm font-semibold">Screenshot
                    <input type="file" name="screenshot" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full text-sm">
                </label>
                <p data-screenshot-status class="mt-2 text-sm text-slate-600"></p>
            </fieldset>
            <fieldset data-service-types="translation afp gsr" hidden>
                <div class="grid grid-cols-2 items-end gap-4 rounded-md bg-slate-50 p-4">
                    <p class="text-sm"><strong data-fixed-rate></strong><br><span data-rate-unit class="text-slate-600"></span></p>
                    <label class="justify-self-end text-sm font-semibold">Quantity
                        <input type="number" name="quantity" min="1" max="999" value="1" required class="{{ $inputClass }} max-w-28">
                    </label>
                </div>
            </fieldset>
            <fieldset data-service-types="visa" hidden class="space-y-3">
                <label data-visa-option class="block text-sm font-semibold">Fee option
                    <select name="option" class="{{ $inputClass }}"></select>
                </label>
                <p data-no-visa-fees class="rounded-md bg-slate-100 p-3 text-sm text-slate-600" hidden>Configure an effective fee for visa subclass 500 in Configuration → Visa Charges.</p>
                @foreach (\App\Models\ApplicationService::VISA_FEES as $key => $label)
                    <div data-visa-line="{{ $key }}" class="grid items-center gap-2 border-b border-slate-200 py-2 sm:grid-cols-[1fr_auto_auto]">
                        <p class="text-sm font-semibold">{{ $label }}<span data-visa-rate class="block font-normal text-slate-600"></span></p>
                        <label class="text-sm">Quantity
                            <input aria-label="{{ $label }} quantity" type="number" @if ($key !== 'primary') name="quantities[{{ $key }}]" @endif min="0" max="999" value="{{ $key === 'primary' ? 1 : 0 }}" @readonly($key === 'primary') class="ml-1 w-20 rounded-md border border-slate-300 px-2 py-1">
                        </label>
                        <strong data-visa-total class="text-right text-sm tabular-nums sm:min-w-24"></strong>
                    </div>
                @endforeach
            </fieldset>
            <div data-service-total hidden class="flex justify-between gap-3 rounded-lg bg-sky-50 px-4 py-3 font-bold">
                <span>Service total (AUD)</span><output class="tabular-nums text-[#1f7890]"></output>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
                <button type="button" data-remove-service hidden class="mr-auto text-sm font-semibold text-red-700 underline">Remove service</button>
                <button type="button" data-service-close class="{{ $buttonClass }}">Cancel</button>
                <button type="submit" class="rounded-md bg-[#1f7890] px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">Add</button>
            </div>
        </form>
    </dialog>
    @include('crm.applications.services-script', ['visaOptions' => \App\Models\ApplicationService::visaOptions()])
@endif
