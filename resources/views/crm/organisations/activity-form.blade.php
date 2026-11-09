<details class="mt-5" @if($errors->any()) open @endif>
    <summary class="w-fit cursor-pointer rounded-md border border-[#1f7890] px-4 py-2 text-sm font-semibold text-[#1f7890]">Add {{ ['tasks' => 'task', 'payments' => 'payment', 'rfi' => 'RFI', 'quotes' => 'quote', 'agreements' => 'agreement', 'lmt' => 'LMT record'][$kind] }}</summary>
    <form method="POST" action="{{ route('applications.activities.store', $application) }}" class="mt-4 max-w-2xl space-y-4">
        @csrf
        <input type="hidden" name="kind" value="{{ $kind }}">
        @include('crm.organisations.errors')
        @foreach(['title' => 'Title', 'reference' => 'Reference', 'status' => 'Status', 'due_date' => 'Due date'] as $field => $label)
            <label class="block text-sm font-semibold" for="activity-{{ $field }}">{{ $label }}{{ $field === 'title' ? ' *' : '' }}
                <input id="activity-{{ $field }}" name="{{ $field }}" type="{{ $field === 'due_date' ? 'date' : 'text' }}" value="{{ old($field) }}" @required($field === 'title') maxlength="{{ $field === 'status' ? 80 : 255 }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 font-normal">
            </label>
        @endforeach
        @if(in_array($kind, ['payments', 'quotes']))
            <div class="grid grid-cols-2 gap-4">
                <label class="text-sm font-semibold" for="activity-amount">Amount *<input id="activity-amount" name="amount" type="number" min="0" max="9999999999.99" step="0.01" required value="{{ old('amount') }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold" for="activity-currency">Currency *<input id="activity-currency" name="currency" required maxlength="3" pattern="[A-Z]{3}" value="{{ old('currency', 'AUD') }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 font-normal"></label>
            </div>
        @endif
        <button type="submit" class="rounded-md bg-[#1f7890] px-4 py-2 text-sm font-semibold text-white">Save record</button>
    </form>
</details>
