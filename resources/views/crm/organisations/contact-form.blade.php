@php $formId = (string) ($person?->id ?? 'new'); @endphp
<form method="POST" action="{{ $person ? route('organisations.contacts.update', [$organisation, $person]) : route('organisations.contacts.store', $organisation) }}" class="mt-4 space-y-3">
    @csrf
    @if($person) @method('PATCH') @endif
    <input type="hidden" name="_contact_form" value="{{ $formId }}">
    @foreach (['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'role' => 'Role'] as $field => $label)
        <label class="block text-sm" for="contact-{{ $formId }}-{{ $field }}">{{ $label }}{{ $field === 'name' ? ' *' : '' }}
            <input id="contact-{{ $formId }}-{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') }}" value="{{ old('_contact_form') === $formId ? old($field) : $person?->$field }}" @required($field === 'name') maxlength="{{ $field === 'phone' ? 40 : 255 }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2">
        </label>
    @endforeach
    <button type="submit" class="rounded-md bg-[#1f7890] px-4 py-2 text-sm font-semibold text-white">Save contact</button>
</form>
