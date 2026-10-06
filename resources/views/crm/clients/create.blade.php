@extends('layouts.payroll')

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

        .client-profile-control.is-invalid {
            border-color: #dc2626;
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

        .client-profile-primary:hover {
            border-color: #98d2e2;
            background: #dff2f8;
        }

        .client-profile-secondary {
            border: 1px solid #b9c1cc;
            background: #fff;
            color: #334155;
        }

        .client-profile-secondary:hover {
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

        .client-profile-avatar-button:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .client-profile-alert {
            border-radius: 8px;
            border: 1px solid;
            padding: 14px 16px;
            font-size: 14px;
            line-height: 1.5;
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

        @media (max-width: 980px) {
            .client-profile-field {
                grid-template-columns: 1fr;
                gap: 4px;
            }
        }
    </style>

    <form id="client-profile-form" class="client-profile" method="POST" action="{{ route('clients.store') }}" novalidate>
        @csrf
        <input id="client-proceed-duplicate" type="hidden" name="proceed_duplicate" value="0">

        <header class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-[26px] font-bold leading-tight text-[#101820]">New Client</h1>
            </div>
            <div class="flex flex-wrap gap-3">
                <button id="client-profile-save" type="submit" class="client-profile-action client-profile-primary">
                    Save changes
                </button>
                <a href="{{ route('clients') }}" class="client-profile-action client-profile-secondary">
                    Discard
                </a>
            </div>
        </header>

        @if ($errors->any())
            <div class="client-profile-alert mb-5 border-rose-200 bg-rose-50 text-rose-700">
                <div class="font-bold">The client was not created. Complete the missing mandatory data.</div>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($duplicateClient)
            <div id="client-duplicate-alert" class="client-profile-alert mb-5 border-amber-200 bg-amber-50 text-amber-800">
                <div class="font-bold">Possible duplicate client found.</div>
                <p class="mt-1">
                    {{ $duplicateClient['name'] }} already exists as CL-{{ $duplicateClient['client_id'] }} with DOB {{ $duplicateClient['dob'] }}.
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button id="client-proceed-duplicate-button" type="button" class="client-profile-action border border-amber-300 bg-white text-amber-800 hover:bg-amber-100">
                        Proceed anyway
                    </button>
                    <button id="client-cancel-duplicate-button" type="button" class="client-profile-action client-profile-secondary">
                        Return to editing
                    </button>
                </div>
            </div>
        @endif

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(280px,0.95fr)_minmax(300px,0.95fr)_minmax(260px,0.78fr)]">
            <div class="client-profile-card p-5">
                <div class="space-y-2">
                    <div class="client-profile-field">
                        <div class="client-profile-label">Client ID</div>
                        <div class="text-sm font-medium text-slate-700">{{ old('client_id', $client['client_id']) }}</div>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-first-name" class="client-profile-label">First Name<span class="client-profile-required">*</span></label>
                        <input id="client-first-name" name="first_name" value="{{ old('first_name', $client['first_name']) }}" type="text" required class="client-profile-control @error('first_name') is-invalid @enderror">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-middle-name" class="client-profile-label">Middle Name</label>
                        <input id="client-middle-name" name="middle_name" value="{{ old('middle_name', $client['middle_name']) }}" type="text" class="client-profile-control">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-surname" class="client-profile-label">Surname</label>
                        <input id="client-surname" name="surname" value="{{ old('surname', $client['surname']) }}" type="text" class="client-profile-control">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-dob" class="client-profile-label">DOB</label>
                        <input id="client-dob" name="dob" value="{{ old('dob', $client['dob']) }}" type="date" class="client-profile-control @error('dob') is-invalid @enderror">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-age" class="client-profile-label">Age</label>
                        <input id="client-age" name="age" value="{{ $client['age'] }}" type="text" readonly placeholder="Calculated" class="client-profile-control border-transparent bg-transparent px-0 italic text-slate-500 shadow-none focus:border-transparent focus:ring-0">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-mobile" class="client-profile-label">Mobile number<span class="client-profile-required">*</span></label>
                        <input id="client-mobile" name="mobile" value="{{ old('mobile', $client['mobile']) }}" type="text" required placeholder="Area code + No." class="client-profile-control @error('mobile') is-invalid @enderror">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-email" class="client-profile-label">Email address<span class="client-profile-required">*</span></label>
                        <input id="client-email" name="email" value="{{ old('email', $client['email']) }}" type="email" required class="client-profile-control @error('email') is-invalid @enderror">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-nationality" class="client-profile-label">Nationality<span class="client-profile-required">*</span></label>
                        <select id="client-nationality" name="nationality" required class="client-profile-control @error('nationality') is-invalid @enderror">
                            <option value="">Option 1</option>
                            @foreach ($nationalities as $nationality)
                                <option value="{{ $nationality }}" @selected(old('nationality', $client['nationality']) === $nationality)>{{ $nationality }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-current-location" class="client-profile-label">Current Location<span class="client-profile-required">*</span></label>
                        <input id="client-current-location" name="current_location" value="{{ old('current_location', $client['current_location']) }}" type="text" required class="client-profile-control @error('current_location') is-invalid @enderror" placeholder="Option 1">
                    </div>
                </div>
            </div>

            <div class="client-profile-card p-5">
                <h2 class="mb-4 text-sm font-extrabold text-[#101820]">Australian Address</h2>
                <div class="space-y-2">
                    <div class="client-profile-field">
                        <label for="client-street" class="client-profile-label">Street Address</label>
                        <input id="client-street" name="street" value="{{ old('street', $client['australian_address']['street']) }}" type="text" class="client-profile-control">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-suburb" class="client-profile-label">Suburb/City</label>
                        <input id="client-suburb" name="suburb" value="{{ old('suburb', $client['australian_address']['suburb']) }}" type="text" class="client-profile-control">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-state" class="client-profile-label">State</label>
                        <select id="client-state" name="state" class="client-profile-control">
                            <option value="">Option 1</option>
                            @foreach ($states as $state)
                                <option value="{{ $state }}" @selected(old('state', $client['australian_address']['state']) === $state)>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-postcode" class="client-profile-label">Postcode</label>
                        <input id="client-postcode" name="postcode" value="{{ old('postcode', $client['australian_address']['postcode']) }}" type="number" class="client-profile-control">
                    </div>
                </div>

                <h2 class="mb-4 mt-9 text-sm font-extrabold text-[#101820]">Overseas Address</h2>
                <textarea id="client-overseas-address" name="overseas_address" rows="5" class="client-profile-control min-h-[120px] resize-y py-3">{{ old('overseas_address', $client['overseas_address']) }}</textarea>
            </div>

            <div class="space-y-4">
                <div class="client-profile-card relative min-h-[164px] overflow-hidden p-4">
                    <div class="absolute right-3 top-3 flex flex-col gap-3">
                        <button type="button" title="Edit photo" class="client-profile-avatar-button bg-[#22c4bb]">
                            {!! payroll_icon('edit', 'h-5 w-5') !!}
                            <span class="sr-only">Edit photo</span>
                        </button>
                        <button type="button" title="Delete photo" class="client-profile-avatar-button bg-[#e65b73]">
                            {!! payroll_icon('trash', 'h-5 w-5') !!}
                            <span class="sr-only">Delete photo</span>
                        </button>
                    </div>
                    <div class="mx-auto mt-3 flex h-[126px] w-[126px] flex-col items-center justify-end overflow-hidden rounded-b-full">
                        <div class="h-[60px] w-[60px] rounded-full bg-[#c4c4c4]"></div>
                        <div class="-mt-1 h-[76px] w-[126px] rounded-t-full bg-[#c4c4c4]"></div>
                    </div>
                </div>

                <div class="client-profile-card p-5">
                    <div class="space-y-2">
                        <div class="client-profile-field">
                            <label for="client-admin-office" class="client-profile-label">Admin Office<span class="client-profile-required">*</span></label>
                            <select id="client-admin-office" name="admin_office" required class="client-profile-control @error('admin_office') is-invalid @enderror">
                                <option value="">Option 1</option>
                                @foreach ($offices as $office)
                                    <option value="{{ $office }}" @selected(old('admin_office', $client['admin_office']) === $office)>{{ $office }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="client-profile-field">
                            <label for="client-status" class="client-profile-label">Client Status<span class="client-profile-required">*</span></label>
                            <select id="client-status" name="client_status" required class="client-profile-control @error('client_status') is-invalid @enderror">
                                <option value="">Option 1</option>
                                @foreach ($clientStatuses as $status)
                                    <option value="{{ $status }}" @selected(old('client_status', $client['client_status']) === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="client-profile-field">
                            <label for="client-current-visa" class="client-profile-label">Current Visa</label>
                            <input id="client-current-visa" name="current_visa" value="{{ old('current_visa', $client['current_visa']) }}" type="text" class="client-profile-control">
                        </div>
                        <div class="client-profile-field">
                            <label for="client-visa-expiry" class="client-profile-label">Visa Expiry</label>
                            <input id="client-visa-expiry" name="visa_expiry" value="{{ old('visa_expiry', $client['visa_expiry']) }}" type="date" class="client-profile-control @error('visa_expiry') is-invalid @enderror">
                        </div>
                    </div>
                </div>
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
                        return $datetime !== '' ? $datetime : 'Pending save';
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
                    $noteRecords = array_values(array_filter(array_map(function ($note) use ($currentNoteAuthor) {
                        $body = trim(is_array($note) ? (string) ($note['body'] ?? '') : (string) $note);
                        if ($body === '') {
                            return null;
                        }
                        return [
                            'body' => $body,
                            'author' => trim(is_array($note) ? (string) ($note['author'] ?? '') : '') ?: $currentNoteAuthor,
                            'datetime' => trim(is_array($note) ? (string) ($note['datetime'] ?? '') : '') ?: 'Pending save',
                            'datetime_display' => trim(is_array($note) ? (string) ($note['datetime_display'] ?? '') : '') ?: 'Pending save',
                        ];
                    }, $client['notes'])));
                }
            @endphp

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-extrabold text-[#101820]">Client Notes</h2>
                <button id="client-add-note" type="button" class="client-profile-action client-profile-primary">
                    Add Note
                </button>
            </div>
            <div id="client-notes-list" class="client-notes-grid">
                @foreach ($noteRecords as $note)
                    <article class="client-note">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div class="text-xs font-extrabold uppercase tracking-[0.08em] text-slate-500">Note</div>
                            <button type="button" class="client-delete-note text-rose-600 transition hover:text-rose-700" title="Delete note">
                                {!! payroll_icon('trash', 'h-4 w-4') !!}
                                <span class="sr-only">Delete note</span>
                            </button>
                        </div>
                        <div class="client-note-meta">Added by {{ $note['author'] }}<br>{{ $note['datetime_display'] }}</div>
                        <input type="hidden" name="note_authors[]" value="{{ $note['author'] }}">
                        <input type="hidden" name="note_datetimes[]" value="{{ $note['datetime'] }}">
                        <textarea name="note_bodies[]" rows="4" class="client-note-text w-full">{{ $note['body'] }}</textarea>
                    </article>
                @endforeach
            </div>
            <div id="client-notes-empty" @class(['client-note-empty', 'mt-2', 'hidden' => $noteRecords !== []])>
                No client notes yet.
            </div>
        </section>
    </form>

    <script>
        (() => {
            const form = document.getElementById('client-profile-form');
            const proceedDuplicate = document.getElementById('client-proceed-duplicate');
            const proceedDuplicateButton = document.getElementById('client-proceed-duplicate-button');
            const cancelDuplicateButton = document.getElementById('client-cancel-duplicate-button');
            const duplicateAlert = document.getElementById('client-duplicate-alert');
            const dob = document.getElementById('client-dob');
            const age = document.getElementById('client-age');
            const notesList = document.getElementById('client-notes-list');
            const notesEmpty = document.getElementById('client-notes-empty');
            const addNote = document.getElementById('client-add-note');
            const currentNoteAuthor = @json($currentNoteAuthor);

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
            const noteTimestampDisplay = () => new Intl.DateTimeFormat('en', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }).format(new Date()).replace(' at ', ' at ');
            const noteTemplate = () => {
                const datetime = noteTimestamp();
                const datetimeDisplay = noteTimestampDisplay();
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
                    <div class="client-note-meta">Added by ${author}<br>${datetimeDisplay}</div>
                    <input type="hidden" name="note_authors[]" value="${author}">
                    <input type="hidden" name="note_datetimes[]" value="${datetime}">
                    <textarea name="note_bodies[]" rows="4" class="client-note-text w-full"></textarea>
                `;

                return note;
            };

            const calculateAge = () => {
                if (! dob.value) {
                    age.value = '';
                    return;
                }

                const birthDate = new Date(`${dob.value}T00:00:00`);
                const today = new Date();
                let years = today.getFullYear() - birthDate.getFullYear();
                const monthDelta = today.getMonth() - birthDate.getMonth();

                if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birthDate.getDate())) {
                    years -= 1;
                }

                age.value = Number.isFinite(years) && years >= 0 ? years : '';
            };

            form.addEventListener('input', () => {
                proceedDuplicate.value = '0';
            });
            form.addEventListener('change', () => {
                proceedDuplicate.value = '0';
                calculateAge();
            });
            proceedDuplicateButton?.addEventListener('click', () => {
                proceedDuplicate.value = '1';
                form.submit();
            });
            cancelDuplicateButton?.addEventListener('click', () => {
                duplicateAlert?.remove();
                proceedDuplicate.value = '0';
                document.getElementById('client-first-name').focus();
            });
            addNote?.addEventListener('click', () => {
                const note = noteTemplate();
                notesList.appendChild(note);
                syncNotesEmptyState();
                note.querySelector('textarea')?.focus();
            });
            notesList?.addEventListener('click', (event) => {
                const deleteButton = event.target.closest('.client-delete-note');

                if (! deleteButton) return;

                deleteButton.closest('.client-note')?.remove();
                syncNotesEmptyState();
            });

            calculateAge();
            syncNotesEmptyState();
        })();
    </script>
@endsection
