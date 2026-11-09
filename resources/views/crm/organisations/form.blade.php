@extends('layouts.payroll')

@section('content')
    <header class="mb-7">
        <a href="{{ $organisation->exists ? route('organisations.show', $organisation) : route('organisations') }}" class="text-sm font-medium text-[#1f7890]">&larr; Organisations</a>
        <h1 class="mt-3 text-[26px] font-bold">{{ $organisation->exists ? 'Edit Organisation' : 'New Organisation' }}</h1>
    </header>
    <form method="POST" action="{{ $organisation->exists ? route('organisations.update', $organisation) : route('organisations.store') }}" class="max-w-3xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @if ($organisation->exists) @method('PATCH') @endif
        <h2 class="text-lg font-bold">Organisation Details</h2>
        @include('crm.organisations.errors')
        @foreach (['legal_name' => 'Legal Name', 'trading_name' => 'Trading Name', 'abn' => 'ABN'] as $field => $label)
            <label class="block text-sm font-semibold" for="{{ $field }}">{{ $label }}{{ $field === 'legal_name' ? ' *' : '' }}
                <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $field === 'legal_name' ? $organisation->name : $organisation->$field) }}" @required($field === 'legal_name') maxlength="{{ $field === 'abn' ? 20 : 255 }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 font-normal">
            </label>
        @endforeach
        <label class="block text-sm font-semibold" for="address">Address
            <textarea id="address" name="address" rows="3" maxlength="2000" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 font-normal">{{ old('address', $organisation->address) }}</textarea>
        </label>
        <button class="rounded-md bg-[#1f7890] px-5 py-2.5 text-sm font-semibold text-white" type="submit">{{ $organisation->exists ? 'Save changes' : 'Create organisation' }}</button>
    </form>
@endsection
