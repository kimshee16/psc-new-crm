@extends('layouts.payroll')

@section('content')
    <style>
        .lead-record {
            color: #101820;
        }

        .lead-panel {
            border: 1px solid #d8dee8;
            border-radius: 4px;
            background: #fff;
        }

        .lead-field {
            display: grid;
            grid-template-columns: 150px minmax(0, 1fr);
            align-items: center;
            gap: 12px;
            min-height: 38px;
        }

        .lead-label {
            color: #334155;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.2;
            text-align: right;
        }

        .lead-control {
            width: 100%;
            min-height: 32px;
            border: 1px solid #aeb7c4;
            border-radius: 3px;
            background: #fff;
            padding: 5px 9px;
            color: #334155;
            font-size: 13px;
            outline: none;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .lead-control:focus {
            border-color: #1f7890;
            box-shadow: 0 0 0 3px rgb(31 120 144 / 14%);
        }

        .lead-control.is-invalid {
            border-color: #dc2626;
        }

        .lead-action {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 800;
            transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease;
        }

        .lead-primary {
            border: 1px solid #b9dce4;
            background: #eaf7fc;
            color: #1f5f74;
        }

        .lead-primary:hover {
            border-color: #1f7890;
            background: #dff2f8;
        }

        .lead-secondary {
            border: 1px solid #b9c1cc;
            background: #fff;
            color: #334155;
        }

        .lead-secondary:hover {
            border-color: #64748b;
        }

        .lead-icon-button {
            display: inline-flex;
            min-height: 30px;
            min-width: 30px;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: 1px solid #b9dce4;
            color: #1f7890;
            transition: border-color 160ms ease, color 160ms ease, background-color 160ms ease;
        }

        .lead-icon-button:hover {
            border-color: #1f7890;
            background: #eef8fa;
        }

        .lead-alert {
            border-radius: 8px;
            border: 1px solid;
            padding: 14px 16px;
            font-size: 14px;
            line-height: 1.5;
        }

        .lead-modal {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgb(15 23 42 / 56%);
            padding: 24px;
        }

        .lead-modal.is-open {
            display: flex;
        }

        @media (max-width: 860px) {
            .lead-field {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            .lead-label {
                text-align: left;
            }
        }
    </style>

    <form id="lead-record-form" class="lead-record" method="POST" action="{{ route('leads.update', $lead['lead_id']) }}" novalidate>
        @csrf
        @method('PATCH')

        <header class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <nav class="text-xs font-bold text-slate-500">
                    <a href="{{ route('clients.show', $lead['client_id']) }}" class="text-[#2472b8] underline">{{ $lead['client_full_name'] }}</a>
                    <span class="mx-1 text-slate-400">&gt;</span>
                    <span>Lead ID {{ $lead['lead_id'] }}</span>
                </nav>
                <h1 class="mt-3 text-[26px] font-bold leading-tight text-[#101820]">Lead ID {{ $lead['lead_id'] }} - {{ $lead['client_full_name'] }}</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="mailto:{{ $lead['email'] }}" class="lead-action lead-primary">
                    {!! payroll_icon('mail', 'h-4 w-4') !!}
                    <span>Email</span>
                </a>
                <a href="https://drive.google.com/drive/search?q={{ urlencode($lead['client_full_name']) }}" target="_blank" rel="noopener" class="lead-action lead-primary">
                    {!! payroll_icon('folder', 'h-4 w-4') !!}
                    <span>Google Drive</span>
                </a>
                <button id="lead-create-application-open" type="button" class="lead-action lead-primary">
                    {!! payroll_icon('briefcase', 'h-4 w-4') !!}
                    <span>Create Application</span>
                </button>
            </div>
        </header>

        @if (session('success'))
            <div class="lead-alert mb-5 border-emerald-200 bg-emerald-50 font-semibold text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="lead-alert mb-5 border-rose-200 bg-rose-50 text-rose-700">
                <div class="font-bold">The lead was not saved. Complete the missing or invalid data.</div>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="lead-panel p-6">
            <h2 class="mb-5 text-sm font-extrabold text-[#101820]">Lead Summary</h2>

            <div class="grid grid-cols-1 gap-x-14 gap-y-3 lg:grid-cols-2">
                <div class="space-y-3">
                    <div class="lead-field">
                        <div class="lead-label">Lead ID</div>
                        <div class="text-sm font-semibold text-slate-700">{{ $lead['lead_id'] }}</div>
                    </div>
                    <div class="lead-field">
                        <div class="lead-label">Client Name</div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('clients.show', $lead['client_id']) }}" class="font-bold text-[#2472b8] underline">{{ $lead['client_full_name'] }}</a>
                            <details class="relative">
                                <summary class="lead-icon-button cursor-pointer" title="Client details">
                                    {!! payroll_icon('eye', 'h-4 w-4') !!}
                                    <span class="sr-only">Client details</span>
                                </summary>
                                <div class="absolute left-0 top-full z-20 mt-2 w-64 rounded-md border border-slate-200 bg-white p-3 text-xs text-slate-600 shadow-lg">
                                    <div><span class="font-bold text-slate-700">DOB:</span> {{ $lead['client_dob'] !== '' ? $lead['client_dob'] : 'Not recorded' }}</div>
                                    <div class="mt-1"><span class="font-bold text-slate-700">Current location:</span> {{ $lead['client_current_location'] }}</div>
                                    <div class="mt-1"><span class="font-bold text-slate-700">Nationality:</span> {{ $lead['client_nationality'] }}</div>
                                </div>
                            </details>
                        </div>
                    </div>
                    <div class="lead-field">
                        <label for="lead-status" class="lead-label">Status</label>
                        <select id="lead-status" name="status" class="lead-control @error('status') is-invalid @enderror">
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(old('status', $lead['status']) === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lead-field">
                        <label for="lead-prospective-service" class="lead-label">Prospective Service</label>
                        <select id="lead-prospective-service" name="prospective_service" class="lead-control @error('prospective_service') is-invalid @enderror">
                            @foreach ($prospectiveServices as $service)
                                <option value="{{ $service }}" @selected(old('prospective_service', $lead['prospective_service']) === $service)>{{ $service }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lead-field">
                        <label for="lead-priority" class="lead-label">Priority</label>
                        <select id="lead-priority" name="priority" class="lead-control @error('priority') is-invalid @enderror">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', $lead['priority']) === $priority)>{{ ucfirst($priority) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lead-field">
                        <label for="lead-critical-date" class="lead-label">Critical Date</label>
                        <input id="lead-critical-date" name="critical_date" value="{{ old('critical_date', $lead['critical_date']) }}" type="date" class="lead-control @error('critical_date') is-invalid @enderror">
                    </div>
                    <div class="lead-field">
                        <label for="lead-source" class="lead-label">Source</label>
                        <select id="lead-source" name="source" class="lead-control @error('source') is-invalid @enderror">
                            @foreach ($sources as $source)
                                <option value="{{ $source }}" @selected(old('source', $lead['source']) === $source)>{{ $source }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="lead-event-field" class="lead-field">
                        <label for="lead-event" class="lead-label">Event</label>
                        <select id="lead-event" name="event_name" class="lead-control @error('event_name') is-invalid @enderror">
                            <option value=""></option>
                            @foreach ($eventOptions as $event)
                                <option value="{{ $event }}" @selected(old('event_name', $lead['event_name']) === $event)>{{ $event }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lead-field">
                        <label for="lead-highest-education" class="lead-label">Highest Ed</label>
                        <select id="lead-highest-education" name="highest_education_level" class="lead-control @error('highest_education_level') is-invalid @enderror">
                            <option value=""></option>
                            @foreach ($educationLevels as $educationLevel)
                                <option value="{{ $educationLevel }}" @selected(old('highest_education_level', $lead['highest_education_level']) === $educationLevel)>{{ $educationLevel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="lead-field">
                        <div class="lead-label">Phone number</div>
                        <div class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <span>{{ $lead['phone_display'] }}</span>
                            <a href="tel:{{ $lead['phone_href'] }}" title="Call phone number" class="lead-icon-button">
                                {!! payroll_icon('phone', 'h-4 w-4') !!}
                                <span class="sr-only">Call phone number</span>
                            </a>
                        </div>
                    </div>
                    <div class="lead-field">
                        <div class="lead-label">Email address</div>
                        <div class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <span>{{ $lead['email'] }}</span>
                            <a href="mailto:{{ $lead['email'] }}" title="Send email" class="lead-icon-button">
                                {!! payroll_icon('mail', 'h-4 w-4') !!}
                                <span class="sr-only">Send email</span>
                            </a>
                        </div>
                    </div>
                    <div class="lead-field">
                        <label for="lead-counsellor" class="lead-label">Counsellor</label>
                        <select id="lead-counsellor" name="assigned_name" class="lead-control @error('assigned_name') is-invalid @enderror">
                            @foreach ($counsellorOptions as $counsellor)
                                <option value="{{ $counsellor }}" @selected(old('assigned_name', $lead['assigned_name']) === $counsellor)>{{ $counsellor }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lead-field">
                        <label for="lead-secondary-counsellor" class="lead-label">Secondary counsellor</label>
                        <select id="lead-secondary-counsellor" name="secondary_counsellor" class="lead-control @error('secondary_counsellor') is-invalid @enderror">
                            <option value=""></option>
                            @foreach ($counsellorOptions as $counsellor)
                                <option value="{{ $counsellor }}" @selected(old('secondary_counsellor', $lead['secondary_counsellor']) === $counsellor)>{{ $counsellor }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lead-field">
                        <label for="lead-migration-agent" class="lead-label">Migration Agent</label>
                        <select id="lead-migration-agent" name="migration_agent" class="lead-control @error('migration_agent') is-invalid @enderror">
                            <option value=""></option>
                            @foreach ($counsellorOptions as $counsellor)
                                <option value="{{ $counsellor }}" @selected(old('migration_agent', $lead['migration_agent']) === $counsellor)>{{ $counsellor }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lead-field">
                        <div class="lead-label">Date Created</div>
                        <div class="text-sm font-semibold text-slate-700">{{ $lead['created_at'] }}</div>
                    </div>
                    <div class="lead-field">
                        <div class="lead-label">Last Modified</div>
                        <div class="text-sm font-semibold text-slate-700">{{ $lead['updated_at'] }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-x-14 lg:grid-cols-2">
                <div class="lead-field lg:col-span-2">
                    <label for="lead-notes" class="lead-label self-start pt-2">Notes</label>
                    <textarea id="lead-notes" name="notes" rows="5" maxlength="4000" class="lead-control min-h-[124px] resize-y @error('notes') is-invalid @enderror">{{ old('notes', $lead['notes']) }}</textarea>
                </div>
            </div>
        </section>

        <footer class="mt-7 flex flex-wrap justify-center gap-5">
            <a href="{{ route('leads') }}" class="lead-action lead-secondary">Back to leads</a>
            <a href="{{ route('clients.show', $lead['client_id']) }}" class="lead-action lead-secondary">View client</a>
            <button type="submit" class="lead-action lead-primary">Save</button>
        </footer>
    </form>

    <div id="lead-create-application-modal" class="lead-modal" aria-hidden="true">
        <div role="dialog" aria-modal="true" aria-labelledby="lead-create-application-title" class="w-full max-w-[520px] rounded-lg bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <h2 id="lead-create-application-title" class="text-xl font-extrabold text-[#101820]">Create Application</h2>
                <button id="lead-create-application-close" type="button" title="Close dialog" class="text-slate-500 transition hover:text-slate-900">
                    <span aria-hidden="true" class="text-2xl leading-none">&times;</span>
                    <span class="sr-only">Close dialog</span>
                </button>
            </div>
            <div class="mt-5 space-y-4">
                <label class="block text-sm font-bold text-slate-700">
                    Application type
                    <select class="lead-control mt-2">
                        <option>Study application</option>
                        <option>Visa application</option>
                        <option>Migration service</option>
                    </select>
                </label>
                <label class="block text-sm font-bold text-slate-700">
                    Initial summary
                    <input type="text" value="{{ $lead['client_full_name'] }} - {{ $lead['prospective_service'] }}" class="lead-control mt-2">
                </label>
            </div>
            <div class="mt-7 flex flex-wrap justify-end gap-3">
                <button id="lead-create-application-cancel" type="button" class="lead-action lead-secondary">Cancel</button>
                <a href="{{ route('applications') }}" class="lead-action lead-primary">Continue</a>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const source = document.getElementById('lead-source');
            const eventField = document.getElementById('lead-event-field');
            const modal = document.getElementById('lead-create-application-modal');
            const openModal = document.getElementById('lead-create-application-open');
            const closeModal = document.getElementById('lead-create-application-close');
            const cancelModal = document.getElementById('lead-create-application-cancel');

            const syncEventField = () => {
                eventField.classList.toggle('hidden', source.value !== 'Event');
            };
            const setModalOpen = (isOpen) => {
                modal.classList.toggle('is-open', isOpen);
                modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            };

            source.addEventListener('change', syncEventField);
            openModal.addEventListener('click', () => setModalOpen(true));
            closeModal.addEventListener('click', () => setModalOpen(false));
            cancelModal.addEventListener('click', () => setModalOpen(false));
            modal.addEventListener('click', (event) => {
                if (event.target === modal) setModalOpen(false);
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setModalOpen(false);
            });

            syncEventField();
        })();
    </script>
@endsection
