@extends('layouts.payroll')

@php
    $clientFullName = trim(preg_replace('/\s+/', ' ', $client['first_name'].' '.$client['middle_name'].' '.$client['surname']));
@endphp

@section('content')
    <style>
        .client-profile {
            color: #101820;
        }

        .client-profile-card {
            border: 1px solid #d8dee8;
            border-radius: 4px;
            background: #fff;
        }

        .client-profile-field {
            display: grid;
            grid-template-columns: 136px minmax(0, 1fr);
            align-items: center;
            gap: 10px;
            min-height: 36px;
        }

        .client-profile-label {
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
        }

        .client-profile-required {
            color: #dc2626;
        }

        .client-profile-control {
            width: 100%;
            min-height: 28px;
            border: 1px solid #aeb7c4;
            border-radius: 3px;
            background: #fff;
            padding: 4px 8px;
            color: #334155;
            font-size: 13px;
            outline: none;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .client-profile-control:focus {
            border-color: #1f7890;
            box-shadow: 0 0 0 3px rgb(31 120 144 / 14%);
        }

        .client-profile-control:disabled {
            border-color: transparent;
            background: transparent;
            padding-left: 0;
            color: #334155;
            opacity: 1;
        }

        select.client-profile-control:disabled {
            appearance: none;
        }

        textarea.client-profile-control:disabled {
            border-color: #d8dee8;
            background: #fff;
            padding: 16px;
        }

        .client-profile-control.is-invalid {
            border-color: #dc2626;
        }

        .client-phone-fields {
            display: grid;
            grid-template-columns: minmax(156px, 0.58fr) minmax(112px, 1fr);
            gap: 8px;
        }

        .client-profile-action {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 12px;
            padding: 9px 22px;
            font-size: 13px;
            font-weight: 700;
            transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease, opacity 160ms ease;
        }

        .client-profile-primary {
            border: 1px solid #cfe8f1;
            background: #eaf7fc;
            color: #475569;
        }

        .client-profile-primary:not(:disabled):hover {
            border-color: #98d2e2;
            background: #dff2f8;
        }

        .client-profile-primary:disabled,
        .client-profile-secondary:disabled {
            cursor: not-allowed;
            opacity: 0.58;
        }

        .client-profile-secondary {
            border: 1px solid #b9c1cc;
            background: #fff;
            color: #334155;
        }

        .client-profile-secondary:not(:disabled):hover {
            border-color: #64748b;
        }

        .client-profile-avatar-button {
            display: inline-flex;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            color: #fff;
            transition: transform 160ms ease, opacity 160ms ease;
        }

        .client-profile-avatar-button:not(:disabled):hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .client-profile-avatar-button:disabled {
            cursor: not-allowed;
            opacity: 0.46;
        }

        .client-passport-photo-frame {
            display: flex;
            min-height: 164px;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 4px;
            background: #fff;
        }

        .client-passport-preview-button {
            display: flex;
            width: 100%;
            min-height: 164px;
            align-items: center;
            justify-content: center;
            border: 0;
            background: transparent;
            padding: 0;
            cursor: zoom-in;
        }

        .client-passport-preview-button:focus-visible {
            outline: 3px solid rgb(31 120 144 / 24%);
            outline-offset: 3px;
        }

        .client-passport-preview-button.hidden {
            display: none;
        }

        .client-passport-photo-frame img,
        .client-passport-preview-button img {
            width: 100%;
            height: 100%;
            min-height: 164px;
            object-fit: cover;
        }

        .client-passport-placeholder {
            display: flex;
            height: 142px;
            width: 142px;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            overflow: hidden;
            border-radius: 0 0 999px 999px;
        }

        .client-passport-placeholder.hidden {
            display: none;
        }

        .client-passport-placeholder-head {
            height: 60px;
            width: 60px;
            border-radius: 999px;
            background: #c4c4c4;
        }

        .client-passport-placeholder-body {
            margin-top: -1px;
            height: 76px;
            width: 126px;
            border-radius: 999px 999px 0 0;
            background: #c4c4c4;
        }

        .client-profile-alert {
            border-radius: 8px;
            border: 1px solid;
            padding: 14px 16px;
            font-size: 14px;
            line-height: 1.5;
        }

        .client-profile-table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
            font-size: 13px;
        }

        .client-profile-table th,
        .client-profile-table td {
            border: 1px solid #d8dee8;
            padding: 12px 18px;
            text-align: left;
        }

        .client-profile-table th {
            color: #475569;
            font-weight: 800;
        }

        .client-notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
        }

        .client-note {
            min-height: 158px;
            border: 1px solid #eadf98;
            border-radius: 6px;
            background: #fff7bd;
            box-shadow: 0 10px 18px rgb(15 23 42 / 8%);
            padding: 14px;
        }

        .client-note:nth-child(3n + 2) {
            background: #dff7ef;
            border-color: #a8dfca;
        }

        .client-note:nth-child(3n) {
            background: #e8f1ff;
            border-color: #bfd2f2;
        }

        .client-note-text {
            min-height: 96px;
            resize: vertical;
            border: 0;
            background: transparent;
            padding: 0;
            color: #334155;
            font-size: 13px;
            line-height: 1.55;
            outline: none;
        }

        .client-note-text:disabled {
            resize: none;
            color: #334155;
            opacity: 1;
        }

        .client-note-meta {
            margin-bottom: 10px;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.4;
        }

        .client-note-empty {
            border: 1px dashed #b8c4d4;
            border-radius: 6px;
            background: #fff;
            padding: 18px;
            color: #64748b;
            font-size: 13px;
        }

        .client-profile-modal {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: none;
            align-items: flex-start;
            justify-content: center;
            background: rgb(15 23 42 / 42%);
            padding: 76px 20px 20px;
        }

        .client-profile-modal.is-open {
            display: flex;
        }

        .client-passport-lightbox {
            align-items: center;
            background: rgb(15 23 42 / 68%);
            padding: 28px;
        }

        .client-passport-lightbox-panel {
            position: relative;
            width: min(920px, 94vw);
            border-radius: 10px;
            background: #fff;
            padding: 18px;
            box-shadow: 0 24px 80px rgb(15 23 42 / 34%);
        }

        .client-passport-lightbox-close {
            position: absolute;
            right: 12px;
            top: 12px;
            display: inline-flex;
            height: 36px;
            width: 36px;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgb(15 23 42 / 72%);
            color: #fff;
            font-size: 26px;
            line-height: 1;
            transition: background-color 160ms ease, transform 160ms ease;
        }

        .client-passport-lightbox-close:hover {
            background: rgb(15 23 42 / 88%);
            transform: translateY(-1px);
        }

        .client-passport-lightbox-image {
            display: block;
            width: 100%;
            max-height: 78vh;
            object-fit: contain;
            border-radius: 6px;
            background: #f8fafc;
        }

        @media (max-width: 980px) {
            .client-profile-field {
                grid-template-columns: 1fr;
                gap: 4px;
            }
        }
    </style>

    <form id="client-edit-form" method="POST" action="{{ route('clients.edit', $client['client_id']) }}">
        @csrf
    </form>
    <form id="client-discard-form" method="POST" action="{{ route('clients.discard', $client['client_id']) }}">
        @csrf
    </form>

    <form id="client-profile-form" class="client-profile" method="POST" action="{{ route('clients.update', $client['client_id']) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PATCH')

        <header class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-[26px] font-bold leading-tight text-[#101820]">{{ $clientFullName }}</h1>
                <div class="sr-only">Client {{ $client['client_id'] }}</div>
            </div>
            <div class="flex flex-wrap gap-3">
                @if ($isEditing)
                    <button id="client-profile-save" type="submit" class="client-profile-action client-profile-primary" disabled>
                        Save changes
                    </button>
                    <button id="client-profile-discard" type="button" class="client-profile-action client-profile-secondary">
                        Discard
                    </button>
                @else
                    <button id="client-profile-edit" type="submit" form="client-edit-form" class="client-profile-action client-profile-primary" @disabled($isLockedByAnother)>
                        {!! payroll_icon('edit', 'h-4 w-4') !!}
                        <span>Edit</span>
                    </button>
                @endif
            </div>
        </header>

        @if (session('success'))
            <div class="client-profile-alert mb-5 border-emerald-200 bg-emerald-50 font-semibold text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="client-profile-alert mb-5 border-amber-200 bg-amber-50 font-semibold text-amber-800">
                {{ session('warning') }}
            </div>
        @endif

        @if ($isLockedByAnother)
            <div class="client-profile-alert mb-5 border-amber-200 bg-amber-50 text-amber-800">
                <div class="font-bold">This client record is currently being edited.</div>
                <p class="mt-1">{{ $editLock['user_name'] ?? 'Another user' }} has the edit lock. The lock will expire at {{ $editLock['expires_at'] ?? 'the configured timeout' }} if it is not released.</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="client-profile-alert mb-5 border-rose-200 bg-rose-50 text-rose-700">
                <div class="font-bold">The client was not saved. Complete the missing or invalid data.</div>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(280px,0.95fr)_minmax(300px,0.95fr)_minmax(260px,0.78fr)]">
            <div class="client-profile-card p-5">
                <div class="space-y-2">
                    <div class="client-profile-field">
                        <div class="client-profile-label">Client ID</div>
                        <div class="text-sm font-medium text-slate-700">{{ $client['client_id'] }}</div>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-first-name" class="client-profile-label">First Name<span class="client-profile-required">*</span></label>
                        <input id="client-first-name" name="first_name" value="{{ old('first_name', $client['first_name']) }}" type="text" required class="client-profile-control @error('first_name') is-invalid @enderror" @disabled(! $isEditing)>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-middle-name" class="client-profile-label">Middle Name</label>
                        <input id="client-middle-name" name="middle_name" value="{{ old('middle_name', $client['middle_name']) }}" type="text" class="client-profile-control @error('middle_name') is-invalid @enderror" @disabled(! $isEditing)>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-surname" class="client-profile-label">Surname</label>
                        <input id="client-surname" name="surname" value="{{ old('surname', $client['surname']) }}" type="text" class="client-profile-control @error('surname') is-invalid @enderror" @disabled(! $isEditing)>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-dob" class="client-profile-label">DOB<span class="client-profile-required">*</span></label>
                        <input id="client-dob" name="dob" value="{{ old('dob', $client['dob']) }}" type="date" max="{{ now()->toDateString() }}" required class="client-profile-control @error('dob') is-invalid @enderror" @disabled(! $isEditing)>
                    </div>
                    <div class="client-profile-field">
                        <div class="client-profile-label">Age</div>
                        <div id="client-age" class="min-h-7 py-1 text-sm text-slate-700">{{ $client['age'] !== '' ? $client['age'] : 'Calculated' }}</div>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-phone-number" class="client-profile-label">Mobile number<span class="client-profile-required">*</span></label>
                        @if ($isEditing)
                            <div class="client-phone-fields">
                                <select id="client-phone-country-code" name="phone_country_code" required class="client-profile-control @error('phone_country_code') is-invalid @enderror">
                                    @foreach ($phoneCountryCodes as $code => $label)
                                        <option value="{{ $code }}" @selected(old('phone_country_code', $client['phone_country_code'] ?? '+61') === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input id="client-phone-number" name="phone_number" value="{{ old('phone_number', $client['phone_number'] ?? '') }}" type="text" required inputmode="tel" maxlength="40" pattern="[0-9 ()-]{6,24}" placeholder="412 345 678" class="client-profile-control @error('phone_number') is-invalid @enderror">
                            </div>
                        @else
                            <div class="text-sm text-slate-700">{{ ($client['phone_country_code'] ?? '+61').($client['phone_number'] ?? '') }}</div>
                        @endif
                    </div>
                    <div class="client-profile-field">
                        <label for="client-email" class="client-profile-label">Email address<span class="client-profile-required">*</span></label>
                        <input id="client-email" name="email" value="{{ old('email', $client['email']) }}" type="email" required class="client-profile-control @error('email') is-invalid @enderror" @disabled(! $isEditing)>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-nationality" class="client-profile-label">Nationality<span class="client-profile-required">*</span></label>
                        <select id="client-nationality" name="nationality" required class="client-profile-control @error('nationality') is-invalid @enderror" @disabled(! $isEditing)>
                            <option value=""></option>
                            @foreach ($nationalities as $nationality)
                                <option value="{{ $nationality }}" @selected(old('nationality', $client['nationality']) === $nationality)>{{ $nationality }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-current-location" class="client-profile-label">Current Location<span class="client-profile-required">*</span></label>
                        <select id="client-current-location" name="current_location" required class="client-profile-control @error('current_location') is-invalid @enderror" @disabled(! $isEditing)>
                            <option value=""></option>
                            @foreach ($currentLocations as $location)
                                <option value="{{ $location }}" @selected(old('current_location', $client['current_location']) === $location)>{{ $location }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="client-profile-card p-5">
                <h2 class="mb-4 text-sm font-extrabold text-[#101820]">Australian Address</h2>
                <div class="space-y-2">
                    <div class="client-profile-field">
                        <label for="client-street" class="client-profile-label">Street Address</label>
                        <input id="client-street" name="street" value="{{ old('street', $client['australian_address']['street']) }}" type="text" class="client-profile-control @error('street') is-invalid @enderror" @disabled(! $isEditing)>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-suburb" class="client-profile-label">Suburb/City</label>
                        <input id="client-suburb" name="suburb" value="{{ old('suburb', $client['australian_address']['suburb']) }}" type="text" class="client-profile-control @error('suburb') is-invalid @enderror" @disabled(! $isEditing)>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-state" class="client-profile-label">State</label>
                        <select id="client-state" name="state" class="client-profile-control @error('state') is-invalid @enderror" @disabled(! $isEditing)>
                            <option value=""></option>
                            @foreach ($states as $state)
                                <option value="{{ $state }}" @selected(old('state', $client['australian_address']['state']) === $state)>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-postcode" class="client-profile-label">Postcode</label>
                        <input id="client-postcode" name="postcode" value="{{ old('postcode', $client['australian_address']['postcode']) }}" type="text" inputmode="numeric" pattern="[0-9]{4}" class="client-profile-control @error('postcode') is-invalid @enderror" @disabled(! $isEditing)>
                    </div>
                </div>

                <h2 class="mb-4 mt-9 text-sm font-extrabold text-[#101820]">Overseas Address</h2>
                <textarea id="client-overseas-address" name="overseas_address" rows="5" class="client-profile-control min-h-[120px] resize-y py-3 @error('overseas_address') is-invalid @enderror" @disabled(! $isEditing)>{{ old('overseas_address', $client['overseas_address']) }}</textarea>
            </div>

            <div class="space-y-4">
                <div class="client-profile-card relative overflow-hidden p-4">
                    <div class="mb-3 text-xs font-bold text-slate-600">Passport photo</div>
                    @if ($isEditing)
                        <div class="absolute right-3 top-3 flex flex-col gap-3">
                            <button id="client-photo-edit" type="button" title="Replace photo" class="client-profile-avatar-button bg-[#22c4bb]">
                                {!! payroll_icon('edit', 'h-5 w-5') !!}
                                <span class="sr-only">Replace photo</span>
                            </button>
                            <button id="client-photo-delete" type="button" title="Delete photo" class="client-profile-avatar-button bg-[#e65b73]">
                                {!! payroll_icon('trash', 'h-5 w-5') !!}
                                <span class="sr-only">Delete photo</span>
                            </button>
                        </div>
                    @endif
                    <input id="client-passport-photo" name="passport_photo" type="file" accept="image/*" class="sr-only" @disabled(! $isEditing)>
                    <input id="client-delete-passport-photo" name="delete_passport_photo" type="hidden" value="0">
                    <div id="client-passport-photo-frame" class="client-passport-photo-frame">
                        <button id="client-passport-preview-open" type="button" title="Preview passport photo" @class(['client-passport-preview-button', 'hidden' => $client['passport_photo_url'] === ''])>
                            <img id="client-passport-photo-preview" src="{{ $client['passport_photo_url'] }}" alt="Passport photo">
                        </button>
                        <div id="client-passport-photo-placeholder" @class(['client-passport-placeholder', 'hidden' => $client['passport_photo_url'] !== ''])>
                            <div class="client-passport-placeholder-head"></div>
                            <div class="client-passport-placeholder-body"></div>
                        </div>
                    </div>
                    @error('passport_photo')
                        <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="client-profile-card p-5">
                    <div class="space-y-2">
                        <div class="client-profile-field">
                            <label for="client-admin-office" class="client-profile-label">Admin Office</label>
                            <select id="client-admin-office" name="admin_office" class="client-profile-control @error('admin_office') is-invalid @enderror" @disabled(! $isEditing)>
                                <option value=""></option>
                                @foreach ($offices as $office)
                                    <option value="{{ $office }}" @selected(old('admin_office', $client['admin_office']) === $office)>{{ $office }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="client-profile-field">
                            <label for="client-status" class="client-profile-label">Client Status<span class="client-profile-required">*</span></label>
                            <select id="client-status" name="client_status" required class="client-profile-control @error('client_status') is-invalid @enderror" @disabled(! $isEditing)>
                                <option value=""></option>
                                @foreach ($clientStatuses as $status)
                                    <option value="{{ $status }}" @selected(old('client_status', $client['client_status']) === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="client-profile-field">
                            <label for="client-current-visa" class="client-profile-label">Current Visa</label>
                            <select id="client-current-visa" name="current_visa" class="client-profile-control @error('current_visa') is-invalid @enderror" @disabled(! $isEditing)>
                                <option value=""></option>
                                @foreach ($visas as $visa)
                                    <option value="{{ $visa }}" @selected(old('current_visa', $client['current_visa']) === $visa)>{{ $visa }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="client-profile-field">
                            <label for="client-visa-expiry" class="client-profile-label">Visa Expiry</label>
                            <input id="client-visa-expiry" name="visa_expiry" value="{{ old('visa_expiry', $client['visa_expiry']) }}" type="date" class="client-profile-control @error('visa_expiry') is-invalid @enderror" @disabled(! $isEditing)>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-11">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-extrabold text-[#101820]">Active Applications and Leads</h2>
                <button type="button" class="client-profile-action client-profile-primary">
                    Create Service
                </button>
            </div>
            <div class="overflow-x-auto rounded border border-[#d8dee8] bg-white">
                <table class="client-profile-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Summary</th>
                            <th>Counsellor</th>
                            <th>Status</th>
                            <th>Critical Date</th>
                            <th>Last modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($client['applications'] as $application)
                            <tr>
                                <td><a href="{{ route('applications') }}" class="font-bold text-[#2472b8] underline">{{ $application['id'] }}</a></td>
                                <td class="font-semibold text-slate-700">{{ $application['summary'] }}</td>
                                <td>{{ $application['counsellor'] }}</td>
                                <td>{{ $application['status'] }}</td>
                                <td class="font-semibold">{{ $application['critical_date'] }}</td>
                                <td>{{ $application['last_modified'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mt-11">
            @php
                $submittedNoteBodies = old('note_bodies');
                $submittedNoteAuthors = old('note_authors', []);
                $submittedNoteDatetimes = old('note_datetimes', []);
                $currentNoteAuthor = trim(auth()->user()?->name ?? '') !== '' ? auth()->user()->name : auth()->user()?->email ?? 'Current user';
                $formatNoteDatetime = function (string $datetime): string {
                    if ($datetime === '' || $datetime === 'Pending save' || $datetime === 'Date not recorded') {
                        return $datetime !== '' ? $datetime : 'Date not recorded';
                    }

                    try {
                        return \Illuminate\Support\Carbon::parse($datetime)->locale('en')->isoFormat('MMMM D, YYYY [at] h:mm A');
                    } catch (\Throwable) {
                        return $datetime;
                    }
                };

                if (is_array($submittedNoteBodies)) {
                    $noteRecords = [];
                    foreach (array_values($submittedNoteBodies) as $index => $noteBody) {
                        $body = trim(is_array($noteBody) ? (string) ($noteBody['body'] ?? '') : (string) $noteBody);
                        if ($body === '') {
                            continue;
                        }
                        $noteRecords[] = [
                            'body' => $body,
                            'author' => trim((string) ($submittedNoteAuthors[$index] ?? '')) ?: $currentNoteAuthor,
                            'datetime' => trim((string) ($submittedNoteDatetimes[$index] ?? '')) ?: now()->format('Y-m-d H:i:s'),
                            'datetime_display' => $formatNoteDatetime(trim((string) ($submittedNoteDatetimes[$index] ?? now()->format('Y-m-d H:i:s')))),
                        ];
                    }
                } else {
                    $noteRecords = array_values(array_filter(array_map(function ($note) use ($currentNoteAuthor, $formatNoteDatetime) {
                        $body = trim(is_array($note) ? (string) ($note['body'] ?? '') : (string) $note);
                        if ($body === '') {
                            return null;
                        }
                        return [
                            'body' => $body,
                            'author' => trim(is_array($note) ? (string) ($note['author'] ?? '') : '') ?: $currentNoteAuthor,
                            'datetime' => trim(is_array($note) ? (string) ($note['datetime'] ?? '') : '') ?: 'Date not recorded',
                            'datetime_display' => trim(is_array($note) ? (string) ($note['datetime_display'] ?? '') : '') ?: $formatNoteDatetime(trim(is_array($note) ? (string) ($note['datetime'] ?? '') : '')),
                        ];
                    }, $client['notes'])));
                }
                $canEditNotes = $isEditing && $canManageClientNotes;
            @endphp

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-extrabold text-[#101820]">Client Notes</h2>
                @if ($canEditNotes)
                    <button id="client-add-note" type="button" class="client-profile-action client-profile-primary">
                        Add Note
                    </button>
                @endif
            </div>
            <div id="client-notes-list" class="client-notes-grid">
                @foreach ($noteRecords as $note)
                    <article class="client-note">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div class="text-xs font-extrabold uppercase tracking-[0.08em] text-slate-500">Note</div>
                            @if ($canEditNotes)
                                <button type="button" class="client-delete-note text-rose-600 transition hover:text-rose-700" title="Delete note">
                                    {!! payroll_icon('trash', 'h-4 w-4') !!}
                                    <span class="sr-only">Delete note</span>
                                </button>
                            @endif
                        </div>
                        <div class="client-note-meta">Added by {{ $note['author'] }}<br>{{ $note['datetime_display'] }}</div>
                        @if ($canEditNotes)
                            <input type="hidden" name="note_authors[]" value="{{ $note['author'] }}">
                            <input type="hidden" name="note_datetimes[]" value="{{ $note['datetime'] }}">
                        @endif
                        <textarea name="note_bodies[]" rows="4" class="client-note-text w-full @error('note_bodies.*') is-invalid @enderror" @disabled(! $canEditNotes)>{{ $note['body'] }}</textarea>
                    </article>
                @endforeach
            </div>
            <div id="client-notes-empty" @class(['client-note-empty', 'mt-2', 'hidden' => $noteRecords !== []])>
                No client notes yet.
            </div>
        </section>
    </form>

    @if ($isEditing)
        <div id="client-discard-modal" class="client-profile-modal" aria-hidden="true">
            <div role="dialog" aria-modal="true" aria-labelledby="client-discard-title" class="w-full max-w-[420px] rounded-xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="client-discard-title" class="text-2xl font-semibold leading-tight text-[#101820]">Discard changes?</h2>
                    <button id="client-discard-close" type="button" title="Close dialog" class="text-slate-500 transition hover:text-slate-900">
                        <span aria-hidden="true" class="text-2xl leading-none">&times;</span>
                        <span class="sr-only">Close dialog</span>
                    </button>
                </div>
                <p class="mt-5 text-base leading-7 text-slate-600">You have unsaved changes. Are you sure you want to discard them?</p>
                <div class="mt-7 flex flex-wrap justify-end gap-3">
                    <button id="client-keep-editing" type="button" class="client-profile-action client-profile-secondary">Keep editing</button>
                    <button id="client-confirm-discard" type="button" class="client-profile-action border border-rose-200 bg-white text-rose-600 hover:border-rose-300 hover:bg-rose-50">Close and discard</button>
                </div>
            </div>
        </div>
    @endif

    <div id="client-passport-lightbox" class="client-profile-modal client-passport-lightbox" aria-hidden="true">
        <div role="dialog" aria-modal="true" aria-labelledby="client-passport-lightbox-title" class="client-passport-lightbox-panel">
            <div class="mb-3 pr-12">
                <h2 id="client-passport-lightbox-title" class="text-lg font-extrabold text-[#101820]">Passport photo</h2>
            </div>
            <button id="client-passport-lightbox-close" type="button" class="client-passport-lightbox-close" title="Close preview">
                <span aria-hidden="true">&times;</span>
                <span class="sr-only">Close preview</span>
            </button>
            <img id="client-passport-lightbox-image" src="{{ $client['passport_photo_url'] }}" alt="Enlarged passport photo" class="client-passport-lightbox-image">
        </div>
    </div>

    <script>
        (() => {
            const isEditing = @json($isEditing);
            const form = document.getElementById('client-profile-form');
            const discardForm = document.getElementById('client-discard-form');
            const saveButton = document.getElementById('client-profile-save');
            const discardButton = document.getElementById('client-profile-discard');
            const modal = document.getElementById('client-discard-modal');
            const confirmDiscard = document.getElementById('client-confirm-discard');
            const dob = document.getElementById('client-dob');
            const age = document.getElementById('client-age');
            const addNote = document.getElementById('client-add-note');
            const notesList = document.getElementById('client-notes-list');
            const notesEmpty = document.getElementById('client-notes-empty');
            const currentNoteAuthor = @json($currentNoteAuthor);
            const photoEdit = document.getElementById('client-photo-edit');
            const photoDelete = document.getElementById('client-photo-delete');
            const photoInput = document.getElementById('client-passport-photo');
            const photoDeleteInput = document.getElementById('client-delete-passport-photo');
            const photoPreview = document.getElementById('client-passport-photo-preview');
            const photoPreviewButton = document.getElementById('client-passport-preview-open');
            const photoPlaceholder = document.getElementById('client-passport-photo-placeholder');
            const passportLightbox = document.getElementById('client-passport-lightbox');
            const passportLightboxImage = document.getElementById('client-passport-lightbox-image');
            const passportLightboxClose = document.getElementById('client-passport-lightbox-close');
            let cleanState = new FormData(form);
            let isLeavingByAction = false;
            let previewObjectUrl = null;
            const syncNotesEmptyState = () => {
                notesEmpty?.classList.toggle('hidden', notesList.children.length > 0);
            };
            const escapeHtml = (value) => String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
            const noteTimestamp = () => {
                const now = new Date();
                const pad = (value) => String(value).padStart(2, '0');

                return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
            };
            const noteTemplate = () => {
                const datetime = noteTimestamp();
                const author = escapeHtml(currentNoteAuthor);
                const note = document.createElement('article');
                note.className = 'client-note';
                note.innerHTML = `
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="text-xs font-extrabold uppercase tracking-[0.08em] text-slate-500">Note</div>
                        <button type="button" class="client-delete-note text-rose-600 transition hover:text-rose-700" title="Delete note">
                            {!! payroll_icon('trash', 'h-4 w-4') !!}
                            <span class="sr-only">Delete note</span>
                        </button>
                    </div>
                    <div class="client-note-meta">Added by ${author}<br>${datetime}</div>
                    <input type="hidden" name="note_authors[]" value="${author}">
                    <input type="hidden" name="note_datetimes[]" value="${datetime}">
                    <textarea name="note_bodies[]" rows="4" class="client-note-text w-full"></textarea>
                `;

                return note;
            };

            const serialise = (data) => Array.from(data.entries())
                .filter(([key]) => ! ['_token', '_method'].includes(key))
                .map(([key, value]) => {
                    if (value instanceof File) {
                        return `${key}:${value.name}:${value.size}:${value.lastModified}`;
                    }

                    return `${key}:${value}`;
                })
                .join('|');
            const isDirty = () => serialise(new FormData(form)) !== serialise(cleanState);
            const syncDirtyState = () => {
                if (saveButton) saveButton.disabled = ! isDirty();
            };
            const showPhotoPlaceholder = () => {
                if (previewObjectUrl) URL.revokeObjectURL(previewObjectUrl);

                previewObjectUrl = null;
                photoPreviewButton?.classList.add('hidden');
                photoPreview?.removeAttribute('src');
                passportLightboxImage?.removeAttribute('src');
                photoPlaceholder?.classList.remove('hidden');
            };
            const showPhotoPreview = (src) => {
                if (! src) {
                    showPhotoPlaceholder();
                    return;
                }

                photoPreview.src = src;
                passportLightboxImage.src = src;
                photoPreviewButton?.classList.remove('hidden');
                photoPlaceholder?.classList.add('hidden');
            };
            const openPassportLightbox = () => {
                if (! photoPreview?.getAttribute('src')) return;

                passportLightbox?.classList.add('is-open');
                passportLightbox?.setAttribute('aria-hidden', 'false');
                passportLightboxClose?.focus();
            };
            const closePassportLightbox = () => {
                passportLightbox?.classList.remove('is-open');
                passportLightbox?.setAttribute('aria-hidden', 'true');
                photoPreviewButton?.focus();
            };
            const openModal = () => {
                modal?.classList.add('is-open');
                modal?.setAttribute('aria-hidden', 'false');
                document.getElementById('client-keep-editing')?.focus();
            };
            const closeModal = () => {
                modal?.classList.remove('is-open');
                modal?.setAttribute('aria-hidden', 'true');
                discardButton?.focus();
            };
            const calculateAge = () => {
                if (! dob?.value) {
                    age.textContent = 'Calculated';
                    return;
                }

                const birthDate = new Date(`${dob.value}T00:00:00`);
                const today = new Date();
                let years = today.getFullYear() - birthDate.getFullYear();
                const monthDelta = today.getMonth() - birthDate.getMonth();

                if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birthDate.getDate())) {
                    years -= 1;
                }

                age.textContent = Number.isFinite(years) && years >= 0 ? years : 'Calculated';
            };

            form.addEventListener('input', syncDirtyState);
            form.addEventListener('change', () => {
                calculateAge();
                syncDirtyState();
            });
            form.addEventListener('submit', () => {
                isLeavingByAction = true;
            });
            discardButton?.addEventListener('click', openModal);
            document.getElementById('client-discard-close')?.addEventListener('click', closeModal);
            document.getElementById('client-keep-editing')?.addEventListener('click', closeModal);
            confirmDiscard?.addEventListener('click', () => {
                isLeavingByAction = true;
                discardForm.submit();
            });
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal?.classList.contains('is-open')) closeModal();
                if (event.key === 'Escape' && passportLightbox?.classList.contains('is-open')) closePassportLightbox();
            });
            photoPreviewButton?.addEventListener('click', openPassportLightbox);
            passportLightboxClose?.addEventListener('click', closePassportLightbox);
            passportLightbox?.addEventListener('click', (event) => {
                if (event.target === passportLightbox) closePassportLightbox();
            });
            addNote?.addEventListener('click', () => {
                const note = noteTemplate();
                notesList.appendChild(note);
                syncNotesEmptyState();
                note.querySelector('textarea')?.focus();
                syncDirtyState();
            });
            notesList?.addEventListener('click', (event) => {
                const deleteButton = event.target.closest('.client-delete-note');

                if (! deleteButton) return;

                deleteButton.closest('.client-note')?.remove();
                syncNotesEmptyState();
                syncDirtyState();
            });
            photoEdit?.addEventListener('click', () => photoInput?.click());
            photoInput?.addEventListener('change', () => {
                const file = photoInput.files?.[0];

                if (! file) {
                    syncDirtyState();
                    return;
                }

                if (previewObjectUrl) URL.revokeObjectURL(previewObjectUrl);

                previewObjectUrl = URL.createObjectURL(file);
                photoDeleteInput.value = '0';
                showPhotoPreview(previewObjectUrl);
                syncDirtyState();
            });
            photoDelete?.addEventListener('click', () => {
                photoInput.value = '';
                photoDeleteInput.value = '1';
                showPhotoPlaceholder();
                syncDirtyState();
            });
            window.addEventListener('pagehide', () => {
                if (! isEditing || isLeavingByAction || ! navigator.sendBeacon) return;

                navigator.sendBeacon(discardForm.action, new FormData(discardForm));
            });

            calculateAge();
            syncNotesEmptyState();
            syncDirtyState();
        })();
    </script>
@endsection
