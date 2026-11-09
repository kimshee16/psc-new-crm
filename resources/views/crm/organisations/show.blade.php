@extends('layouts.payroll')

@section('content')
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500"><a href="{{ route('organisations') }}" class="font-medium text-[#15638e] underline">Organisations</a> / {{ $organisation->trading_name ?: $organisation->name }}</p>
            <h1 class="mt-2 text-[30px] font-bold leading-tight">{{ $organisation->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">Organisation record <span class="ml-2 text-xs">{{ $organisation->reference }}</span></p>
        </div>
        <a href="{{ route('organisations.edit', $organisation) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold">{!! payroll_icon('edit', 'h-4 w-4') !!} Edit details</a>
    </header>
    @if(session('success'))<p role="status" class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>@endif
    @include('crm.organisations.errors')
    <div class="grid items-stretch gap-6 xl:grid-cols-[2fr_3fr]">
        <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="mb-5 text-lg font-bold">Organisation details</h2>
            <dl class="grid grid-cols-[minmax(85px,1fr)_2fr] gap-x-5 gap-y-4 text-sm">
                @foreach(['name' => 'Legal name', 'trading_name' => 'Trading name', 'abn' => 'ABN', 'address' => 'Address'] as $field => $label)
                    <dt class="text-slate-500">{{ $label }}</dt><dd class="whitespace-pre-line break-words font-medium">{{ $organisation->$field ?: '—' }}</dd>
                @endforeach
            </dl>
        </section>
        <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold">Contact persons</h2>
                <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold" onclick="const editor = document.getElementById('new-contact'); editor.hidden = !editor.hidden; this.setAttribute('aria-expanded', !editor.hidden); if(!editor.hidden) editor.querySelector('input[name=name]').focus();" aria-controls="new-contact" aria-expanded="{{ old('_contact_form') === 'new' ? 'true' : 'false' }}"><span aria-hidden="true" class="text-xl leading-none">+</span> Add new</button>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr>@foreach(['Name', 'Email', 'Phone', 'Role'] as $label)<th scope="col" class="px-3 py-3">{{ $label }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($organisation->contacts as $person)
                            <tr class="align-top">
                                <td class="px-3 py-4"><button type="button" class="text-left font-semibold hover:text-[#15638e] hover:underline" onclick="const editor = document.getElementById('contact-editor-{{ $person->id }}'); editor.hidden = !editor.hidden; this.setAttribute('aria-expanded', !editor.hidden);" aria-label="Edit contact {{ $person->name }}" aria-controls="contact-editor-{{ $person->id }}" aria-expanded="{{ old('_contact_form') === (string) $person->id ? 'true' : 'false' }}">{{ $person->name }}</button></td>
                                <td class="break-all px-3 py-4">{{ $person->email ?: '—' }}</td><td class="px-3 py-4">{{ $person->phone ?: '—' }}</td><td class="px-3 py-4">{{ $person->role ?: '—' }}</td>
                            </tr>
                            <tr id="contact-editor-{{ $person->id }}" @if(old('_contact_form') !== (string) $person->id) hidden @endif><td colspan="4" class="bg-slate-50 p-4">@include('crm.organisations.contact-form', ['person' => $person])</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">No contact persons added.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div id="new-contact" class="mt-4" @if(old('_contact_form') !== 'new') hidden @endif>
                @include('crm.organisations.contact-form', ['person' => null])
            </div>
        </section>
    </div>
    @include('crm.organisations.application-tables')
    @include('crm.organisations.add-sponsorship-modal')
    @include('crm.organisations.create-nomination-modal')
    <section id="organisation-tabs" class="mt-6 min-w-0 rounded-xl border border-slate-200 bg-white">
        <nav aria-label="Organisation tabs" class="flex flex-wrap border-b border-slate-200 px-4 pt-1">
            @foreach($tabs as $key => $label)
                <a href="{{ route('organisations.show', ['organisation' => $organisation, 'tab' => $key]) }}#organisation-tabs" @if($activeTab === $key) aria-current="page" @endif class="border-b-[3px] px-4 py-4 text-sm font-semibold {{ $activeTab === $key ? 'border-[#15638e] text-slate-800' : 'border-transparent text-slate-500 hover:text-[#15638e]' }}">{{ $label }} @if($counts[$key] ?? 0)<span class="ml-1 text-xs text-slate-500">({{ $counts[$key] }})</span>@endif</a>
            @endforeach
        </nav>
        <div class="p-6">
            @if($activeTab === 'tasks')
                @include('crm.organisations.tasks')
            @elseif($activeTab === 'notes')
                @include('crm.organisations.notes')
            @else
                <h2 class="text-lg font-bold">{{ $tabs[$activeTab] }}</h2>
                <p class="mt-2 text-sm text-slate-500">Collated across sponsorship and nomination records. Add {{ strtolower($tabs[$activeTab]) }} from the linked application.</p>
                <x-applications.activity-records :activities="$activities" :kind="$activeTab" :show-application="true" />
            @endif
        </div>
    </section>
@endsection
