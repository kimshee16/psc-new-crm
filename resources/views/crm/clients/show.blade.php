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

        .client-profile-primary:disabled {
            cursor: not-allowed;
            opacity: 0.58;
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

        @media (max-width: 980px) {
            .client-profile-field {
                grid-template-columns: 1fr;
                gap: 4px;
            }
        }
    </style>

    <form id="client-profile-form" class="client-profile" method="POST" action="#">
        @csrf
        <header class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-[26px] font-bold leading-tight text-[#101820]">{{ $client['first_name'] }} {{ $client['surname'] }}</h1>
                <div class="sr-only">Client {{ $client['client_id'] }}</div>
            </div>
            <div class="flex flex-wrap gap-3">
                <button id="client-profile-save" type="submit" class="client-profile-action client-profile-primary" disabled>
                    Save changes
                </button>
                <button id="client-profile-discard" type="button" class="client-profile-action client-profile-secondary">
                    Discard
                </button>
            </div>
        </header>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(280px,0.95fr)_minmax(300px,0.95fr)_minmax(260px,0.78fr)]">
            <div class="client-profile-card p-5">
                <div class="space-y-2">
                    <div class="client-profile-field">
                        <div class="client-profile-label">Client ID</div>
                        <div class="text-sm font-medium text-slate-700">{{ $client['client_id'] }}</div>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-first-name" class="client-profile-label">First Name<span class="client-profile-required">*</span></label>
                        <input id="client-first-name" name="first_name" value="{{ $client['first_name'] }}" type="text" required class="client-profile-control">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-middle-name" class="client-profile-label">Middle Name</label>
                        <input id="client-middle-name" name="middle_name" value="{{ $client['middle_name'] }}" type="text" class="client-profile-control">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-surname" class="client-profile-label">Surname</label>
                        <input id="client-surname" name="surname" value="{{ $client['surname'] }}" type="text" class="client-profile-control">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-dob" class="client-profile-label">DOB</label>
                        <input id="client-dob" name="dob" value="{{ $client['dob'] }}" type="date" class="client-profile-control">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-age" class="client-profile-label">Age</label>
                        <input id="client-age" name="age" value="{{ $client['age'] }}" type="text" readonly placeholder="Calculated" class="client-profile-control border-transparent bg-transparent px-0 italic text-slate-500 shadow-none focus:border-transparent focus:ring-0">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-mobile" class="client-profile-label">Mobile number<span class="client-profile-required">*</span></label>
                        <input id="client-mobile" name="mobile" value="{{ $client['mobile'] }}" type="text" required placeholder="Area code + No." class="client-profile-control">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-email" class="client-profile-label">Email address<span class="client-profile-required">*</span></label>
                        <input id="client-email" name="email" value="{{ $client['email'] }}" type="email" required class="client-profile-control">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-nationality" class="client-profile-label">Nationality<span class="client-profile-required">*</span></label>
                        <select id="client-nationality" name="nationality" required class="client-profile-control">
                            <option value="">Option 1</option>
                            @foreach ($nationalities as $nationality)
                                <option value="{{ $nationality }}" @selected($client['nationality'] === $nationality)>{{ $nationality }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-current-location" class="client-profile-label">Current Location<span class="client-profile-required">*</span></label>
                        <input id="client-current-location" name="current_location" value="{{ $client['current_location'] }}" type="text" required class="client-profile-control" placeholder="Option 1">
                    </div>
                </div>
            </div>

            <div class="client-profile-card p-5">
                <h2 class="mb-4 text-sm font-extrabold text-[#101820]">Australian Address</h2>
                <div class="space-y-2">
                    <div class="client-profile-field">
                        <label for="client-street" class="client-profile-label">Street Address</label>
                        <input id="client-street" name="street" value="{{ $client['australian_address']['street'] }}" type="text" class="client-profile-control">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-suburb" class="client-profile-label">Suburb/City</label>
                        <input id="client-suburb" name="suburb" value="{{ $client['australian_address']['suburb'] }}" type="text" class="client-profile-control">
                    </div>
                    <div class="client-profile-field">
                        <label for="client-state" class="client-profile-label">State</label>
                        <select id="client-state" name="state" class="client-profile-control">
                            <option value="">Option 1</option>
                            @foreach ($states as $state)
                                <option value="{{ $state }}" @selected($client['australian_address']['state'] === $state)>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="client-profile-field">
                        <label for="client-postcode" class="client-profile-label">Postcode</label>
                        <input id="client-postcode" name="postcode" value="{{ $client['australian_address']['postcode'] }}" type="number" class="client-profile-control">
                    </div>
                </div>

                <h2 class="mb-4 mt-9 text-sm font-extrabold text-[#101820]">Overseas Address</h2>
                <textarea id="client-overseas-address" name="overseas_address" rows="5" class="client-profile-control min-h-[120px] resize-y py-3">{{ $client['overseas_address'] }}</textarea>
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
                            <label for="client-admin-office" class="client-profile-label">Admin Office</label>
                            <select id="client-admin-office" name="admin_office" class="client-profile-control">
                                <option value="">Option 1</option>
                                @foreach ($offices as $office)
                                    <option value="{{ $office }}" @selected($client['admin_office'] === $office)>{{ $office }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="client-profile-field">
                            <label for="client-status" class="client-profile-label">Client Status</label>
                            <select id="client-status" name="client_status" class="client-profile-control">
                                <option value="">Option 1</option>
                                @foreach ($clientStatuses as $status)
                                    <option value="{{ $status }}" @selected($client['client_status'] === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="client-profile-field">
                            <label for="client-current-visa" class="client-profile-label">Current Visa</label>
                            <input id="client-current-visa" name="current_visa" value="{{ $client['current_visa'] }}" type="text" class="client-profile-control">
                        </div>
                        <div class="client-profile-field">
                            <label for="client-visa-expiry" class="client-profile-label">Visa Expiry</label>
                            <input id="client-visa-expiry" name="visa_expiry" value="{{ $client['visa_expiry'] }}" type="date" class="client-profile-control">
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
            <h2 class="mb-4 text-lg font-extrabold text-[#101820]">Client Notes</h2>
            <textarea id="client-notes" name="notes" rows="6" class="client-profile-control min-h-[142px] resize-y rounded border-[#d8dee8] bg-white p-4">{{ implode("\n\n", $client['notes']) }}</textarea>
            <div class="mt-2 flex justify-end">
                <button id="client-add-note" type="button" class="client-profile-action client-profile-primary">
                    Add Note
                </button>
            </div>
        </section>
    </form>

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

    <script>
        (() => {
            const form = document.getElementById('client-profile-form');
            const saveButton = document.getElementById('client-profile-save');
            const discardButton = document.getElementById('client-profile-discard');
            const modal = document.getElementById('client-discard-modal');
            const closeButtons = [
                document.getElementById('client-discard-close'),
                document.getElementById('client-keep-editing'),
            ];
            const confirmDiscard = document.getElementById('client-confirm-discard');
            const dob = document.getElementById('client-dob');
            const age = document.getElementById('client-age');
            const notes = document.getElementById('client-notes');
            const addNote = document.getElementById('client-add-note');
            let cleanState = new FormData(form);

            const serialise = (data) => Array.from(data.entries())
                .map(([key, value]) => `${key}:${value}`)
                .join('|');
            const isDirty = () => serialise(new FormData(form)) !== serialise(cleanState);
            const syncDirtyState = () => {
                saveButton.disabled = ! isDirty();
            };
            const openModal = () => {
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.getElementById('client-keep-editing').focus();
            };
            const closeModal = () => {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                discardButton.focus();
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

            form.addEventListener('input', syncDirtyState);
            form.addEventListener('change', () => {
                calculateAge();
                syncDirtyState();
            });
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                cleanState = new FormData(form);
                syncDirtyState();
            });
            discardButton.addEventListener('click', () => {
                if (isDirty()) {
                    openModal();
                    return;
                }

                form.reset();
                calculateAge();
                syncDirtyState();
            });
            closeButtons.forEach((button) => button.addEventListener('click', closeModal));
            confirmDiscard.addEventListener('click', () => {
                form.reset();
                calculateAge();
                cleanState = new FormData(form);
                syncDirtyState();
                closeModal();
            });
            modal.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
            });
            addNote.addEventListener('click', () => {
                const timestamp = new Date().toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
                notes.value = `${notes.value.trim()}${notes.value.trim() ? '\n\n' : ''}New note - ${timestamp}`;
                notes.focus();
                syncDirtyState();
            });

            calculateAge();
            syncDirtyState();
        })();
    </script>
@endsection
