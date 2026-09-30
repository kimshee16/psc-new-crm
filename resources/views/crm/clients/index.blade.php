@extends('layouts.payroll')

@section('content')
    <header class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="text-xs font-bold uppercase tracking-[0.12em] text-[#1f7890]">PSC-41</div>
            <h1 class="mt-2 text-[26px] font-bold leading-tight text-[#101820]">My Clients</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('clients.search') }}" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-[#1f7890] hover:text-[#1f7890]">
                {!! payroll_icon('search', 'h-4 w-4') !!}
                <span>Client search</span>
            </a>
            <a id="clients-export-link" href="{{ route('clients.export', request()->except('page')) }}" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-[#1f7890] hover:text-[#1f7890]">
                {!! payroll_icon('download', 'h-4 w-4') !!}
                <span>Download CSV</span>
            </a>
            <a href="{{ route('clients.create') }}" class="inline-flex min-h-10 items-center gap-2 rounded-md bg-[#1f7890] px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#17657a]">
                {!! payroll_icon('plus', 'h-4 w-4') !!}
                <span>Add client</span>
            </a>
        </div>
    </header>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4 text-sm text-slate-600">
            <div id="clients-count">Showing 0 clients</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.08em] text-slate-500">
                    <tr class="align-top">
                        <th class="min-w-40 px-5 py-3 font-bold">
                            <button type="button" data-sort="client_id" class="clients-sort inline-flex items-center gap-1 text-left font-bold uppercase tracking-[0.08em] text-slate-600 hover:text-[#1f7890]">
                                <span>Client ID</span>
                                <span data-sort-indicator="client_id" class="inline-flex h-4 w-4 items-center justify-center" aria-hidden="true"></span>
                                <span data-sort-label="client_id" class="sr-only">sorted ascending</span>
                            </button>
                        </th>
                        <th class="min-w-52 px-5 py-3 font-bold">
                            <button type="button" data-sort="first_name" class="clients-sort inline-flex items-center gap-1 text-left font-bold uppercase tracking-[0.08em] text-slate-600 hover:text-[#1f7890]">
                                <span>First Name</span>
                                <span data-sort-indicator="first_name" class="inline-flex h-4 w-4 items-center justify-center" aria-hidden="true"></span>
                                <span data-sort-label="first_name" class="sr-only">not sorted</span>
                            </button>
                            <input id="clients-first-name" value="{{ request('first_name') }}" type="search" placeholder="Type a few characters" class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm font-medium normal-case tracking-normal text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                        </th>
                        <th class="min-w-52 px-5 py-3 font-bold">
                            <button type="button" data-sort="middle_name" class="clients-sort inline-flex items-center gap-1 text-left font-bold uppercase tracking-[0.08em] text-slate-600 hover:text-[#1f7890]">
                                <span>Middle Name</span>
                                <span data-sort-indicator="middle_name" class="inline-flex h-4 w-4 items-center justify-center" aria-hidden="true"></span>
                                <span data-sort-label="middle_name" class="sr-only">not sorted</span>
                            </button>
                            <input id="clients-middle-name" value="{{ request('middle_name') }}" type="search" placeholder="Type a few characters" class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm font-medium normal-case tracking-normal text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                        </th>
                        <th class="min-w-52 px-5 py-3 font-bold">
                            <button type="button" data-sort="surname" class="clients-sort inline-flex items-center gap-1 text-left font-bold uppercase tracking-[0.08em] text-slate-600 hover:text-[#1f7890]">
                                <span>Surname</span>
                                <span data-sort-indicator="surname" class="inline-flex h-4 w-4 items-center justify-center" aria-hidden="true"></span>
                                <span data-sort-label="surname" class="sr-only">not sorted</span>
                            </button>
                            <input id="clients-surname" value="{{ request('surname') }}" type="search" placeholder="Type a few characters" class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm font-medium normal-case tracking-normal text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                        </th>
                        <th class="min-w-52 px-5 py-3 font-bold">
                            <div>Mobile Number</div>
                            <input id="clients-mobile" value="{{ request('mobile') }}" type="search" placeholder="Type a few numbers" class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm font-medium normal-case tracking-normal text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                        </th>
                        <th class="min-w-52 px-5 py-3 font-bold">
                            <div>Status</div>
                            <div class="relative mt-2">
                                <button id="clients-statuses" type="button" aria-haspopup="listbox" aria-expanded="false" class="flex h-10 w-full items-center justify-between gap-2 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium normal-case tracking-normal text-slate-700 outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                                    <span id="clients-statuses-label" class="truncate">
                                        @if (count($selectedStatuses) === 0)
                                            All statuses
                                        @elseif (count($selectedStatuses) === 1)
                                            {{ $selectedStatuses[0] }}
                                        @else
                                            {{ count($selectedStatuses) }} selected
                                        @endif
                                    </span>
                                    {!! payroll_icon('arrow-right', 'h-4 w-4 shrink-0 rotate-90 text-slate-500') !!}
                                </button>
                                <div id="clients-statuses-menu" role="listbox" aria-multiselectable="true" class="absolute left-0 top-full z-30 mt-1 hidden w-full rounded-md border border-slate-200 bg-white py-1 normal-case tracking-normal shadow-lg">
                                    @foreach ($statuses as $status)
                                        <label role="option" aria-selected="{{ in_array($status, $selectedStatuses, true) ? 'true' : 'false' }}" class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                            <input type="checkbox" value="{{ $status }}" data-status-option class="h-4 w-4 rounded border-slate-300 text-[#1f7890] focus:ring-[#1f7890]" @checked(in_array($status, $selectedStatuses, true))>
                                            <span>{{ $status }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </th>
                        <th class="min-w-28 px-5 py-3 font-bold">Tag</th>
                        <th class="min-w-28 px-5 py-3 text-right font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody id="clients-table-body" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>

        <div id="clients-empty" class="hidden px-5 py-10 text-center text-sm text-slate-500">
            No clients match the current filters.
        </div>

        <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
            <div id="clients-page-summary" class="text-sm text-slate-600"></div>
            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-sm font-medium text-slate-600">
                    <span>Rows</span>
                    <select id="clients-per-page" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex flex-wrap gap-2" id="clients-pagination"></div>
            </div>
        </div>
    </section>

    <script>
        (() => {
            const clients = @json($clients);
            const routes = {
                export: @json(route('clients.export')),
                show: @json(route('clients.show', ['clientId' => '__CLIENT_ID__'])),
            };
            const state = {
                first_name: @json(request('first_name', '')),
                middle_name: @json(request('middle_name', '')),
                surname: @json(request('surname', '')),
                mobile: @json(request('mobile', '')),
                statuses: @json($selectedStatuses),
                sort: @json($sort),
                direction: @json($direction),
                perPage: @json($perPage),
                page: 1,
            };
            const elements = {
                firstName: document.getElementById('clients-first-name'),
                middleName: document.getElementById('clients-middle-name'),
                surname: document.getElementById('clients-surname'),
                mobile: document.getElementById('clients-mobile'),
                statuses: document.getElementById('clients-statuses'),
                statusesLabel: document.getElementById('clients-statuses-label'),
                statusesMenu: document.getElementById('clients-statuses-menu'),
                statusChecks: Array.from(document.querySelectorAll('[data-status-option]')),
                perPage: document.getElementById('clients-per-page'),
                tbody: document.getElementById('clients-table-body'),
                empty: document.getElementById('clients-empty'),
                count: document.getElementById('clients-count'),
                pageSummary: document.getElementById('clients-page-summary'),
                pagination: document.getElementById('clients-pagination'),
                exportLink: document.getElementById('clients-export-link'),
            };
            const searchableFields = ['first_name', 'middle_name', 'surname', 'mobile'];
            const sortIcons = {
                asc: '<svg width="24" height="24" class="h-3.5 w-3.5 text-[#1f7890]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>',
                desc: '<svg width="24" height="24" class="h-3.5 w-3.5 text-[#1f7890]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>',
                idle: '<svg width="24" height="24" class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7 9 5-5 5 5"/><path d="m7 15 5 5 5-5"/></svg>',
            };
            const sortLabels = {
                asc: 'sorted ascending',
                desc: 'sorted descending',
                idle: 'not sorted',
            };

            const normalise = (value) => String(value ?? '').toLowerCase().trim();
            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
            const statusClasses = (status) => {
                if (status === 'Active') return 'bg-emerald-50 text-emerald-700';
                if (status === 'Prospect') return 'bg-blue-50 text-blue-700';
                if (status === 'On hold') return 'bg-amber-50 text-amber-700';

                return 'bg-slate-100 text-slate-600';
            };
            const selectedStatuses = () => elements.statusChecks
                .filter((checkbox) => checkbox.checked)
                .map((checkbox) => checkbox.value);
            const updateStatusLabel = () => {
                if (state.statuses.length === 0) {
                    elements.statusesLabel.textContent = 'All statuses';

                    return;
                }

                elements.statusesLabel.textContent = state.statuses.length === 1
                    ? state.statuses[0]
                    : `${state.statuses.length} selected`;
            };
            const syncStatusChecks = () => {
                elements.statusChecks.forEach((checkbox) => {
                    checkbox.checked = state.statuses.includes(checkbox.value);
                    checkbox.closest('[role="option"]').setAttribute('aria-selected', checkbox.checked ? 'true' : 'false');
                });
                updateStatusLabel();
            };
            const closeStatusMenu = () => {
                elements.statusesMenu.classList.add('hidden');
                elements.statuses.setAttribute('aria-expanded', 'false');
            };
            const filteredClients = () => clients
                .filter((client) => searchableFields.every((field) => {
                    const filterValue = normalise(state[field]);

                    return filterValue === '' || normalise(client[field]).includes(filterValue);
                }))
                .filter((client) => state.statuses.length === 0 || state.statuses.includes(client.status))
                .sort((left, right) => {
                    const result = state.sort === 'client_id'
                        ? Number(left[state.sort]) - Number(right[state.sort])
                        : String(left[state.sort]).localeCompare(String(right[state.sort]), undefined, { sensitivity: 'base' });

                    return state.direction === 'desc' ? -result : result;
                });
            const renderRows = (visibleClients) => {
                elements.tbody.innerHTML = visibleClients.map((client) => {
                    const showUrl = routes.show.replace('__CLIENT_ID__', encodeURIComponent(client.client_id));

                    return `
                        <tr>
                            <td class="px-5 py-4 font-bold text-[#101820]">CL-${escapeHtml(client.client_id)}</td>
                            <td class="px-5 py-4 text-slate-700">${escapeHtml(client.first_name)}</td>
                            <td class="px-5 py-4 text-slate-700">${escapeHtml(client.middle_name)}</td>
                            <td class="px-5 py-4 font-semibold text-[#101820]">${escapeHtml(client.surname)}</td>
                            <td class="px-5 py-4 text-slate-600">${escapeHtml(client.mobile)}</td>
                            <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold ${statusClasses(client.status)}">${escapeHtml(client.status)}</span></td>
                            <td class="px-5 py-4"><span class="rounded-md bg-[#eef8fa] px-2 py-1 text-xs font-bold text-[#1f7890]">${escapeHtml(client.tag)}</span></td>
                            <td class="px-5 py-4 text-right">
                                <a href="${showUrl}" title="Open client record" class="inline-flex min-h-9 items-center justify-center rounded-md border border-[#b9dce4] bg-white px-3 py-2 text-sm font-medium text-[#1f7890] transition hover:border-[#1f7890]">
                                    {!! payroll_icon('eye', 'h-4 w-4') !!}
                                    <span class="sr-only">Open client record</span>
                                </a>
                            </td>
                        </tr>
                    `;
                }).join('');
            };
            const queryParams = () => {
                const params = new URLSearchParams();

                searchableFields.forEach((field) => {
                    if (state[field]) params.set(field, state[field]);
                });
                state.statuses.forEach((status) => params.append('statuses[]', status));
                params.set('sort', state.sort);
                params.set('direction', state.direction);
                params.set('per_page', state.perPage);

                return params;
            };
            const syncUrlAndExport = () => {
                const params = queryParams();
                const query = params.toString();

                elements.exportLink.href = `${routes.export}${query ? `?${query}` : ''}`;
                window.history.replaceState({}, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
            };
            const renderPagination = (total, totalPages) => {
                const buttonClass = 'inline-flex min-h-9 items-center rounded-md border px-3 py-2 text-sm font-medium transition';
                const pageButton = (label, page, disabled = false, active = false) => `
                    <button type="button" data-page="${page}" ${disabled ? 'disabled' : ''} class="${buttonClass} ${active ? 'border-[#1f7890] bg-[#1f7890] text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-[#1f7890] hover:text-[#1f7890]'} ${disabled ? 'cursor-not-allowed opacity-50' : ''}">
                        ${label}
                    </button>
                `;
                let html = pageButton('Previous', Math.max(state.page - 1, 1), state.page === 1);

                for (let page = 1; page <= totalPages; page += 1) {
                    html += pageButton(String(page), page, false, page === state.page);
                }

                html += pageButton('Next', Math.min(state.page + 1, totalPages), state.page === totalPages);
                elements.pagination.innerHTML = total > 0 ? html : '';
            };
            const updateSortIndicators = () => {
                document.querySelectorAll('[data-sort-indicator]').forEach((indicator) => {
                    const column = indicator.getAttribute('data-sort-indicator');
                    const mode = column === state.sort ? state.direction : 'idle';
                    const label = document.querySelector(`[data-sort-label="${column}"]`);

                    indicator.innerHTML = sortIcons[mode];
                    if (label) label.textContent = sortLabels[mode];
                });
            };
            const render = () => {
                const results = filteredClients();
                const totalPages = Math.max(Math.ceil(results.length / state.perPage), 1);

                if (state.page > totalPages) state.page = totalPages;

                const start = results.length === 0 ? 0 : (state.page - 1) * state.perPage + 1;
                const end = Math.min(state.page * state.perPage, results.length);
                const visibleClients = results.slice(start === 0 ? 0 : start - 1, end);

                renderRows(visibleClients);
                renderPagination(results.length, totalPages);
                updateSortIndicators();
                syncStatusChecks();
                syncUrlAndExport();

                elements.empty.classList.toggle('hidden', results.length !== 0);
                elements.count.textContent = results.length === 0 ? 'Showing 0 clients' : `Showing ${start}-${end} of ${results.length} clients`;
                elements.pageSummary.textContent = results.length === 0 ? '' : `Page ${state.page} of ${totalPages}`;
            };
            const updateFilter = (field, value) => {
                state[field] = value;
                state.page = 1;
                render();
            };

            elements.firstName.addEventListener('input', (event) => updateFilter('first_name', event.target.value));
            elements.middleName.addEventListener('input', (event) => updateFilter('middle_name', event.target.value));
            elements.surname.addEventListener('input', (event) => updateFilter('surname', event.target.value));
            elements.mobile.addEventListener('input', (event) => updateFilter('mobile', event.target.value));
            elements.statuses.addEventListener('click', () => {
                const isOpen = ! elements.statusesMenu.classList.contains('hidden');

                elements.statusesMenu.classList.toggle('hidden', isOpen);
                elements.statuses.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            });
            elements.statusChecks.forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    state.statuses = selectedStatuses();
                    state.page = 1;
                    render();
                });
            });
            document.addEventListener('click', (event) => {
                if (elements.statuses.contains(event.target) || elements.statusesMenu.contains(event.target)) return;

                closeStatusMenu();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;

                closeStatusMenu();
            });
            elements.perPage.addEventListener('change', (event) => {
                state.perPage = Number(event.target.value);
                state.page = 1;
                render();
            });
            document.querySelectorAll('.clients-sort').forEach((button) => {
                button.addEventListener('click', () => {
                    const sort = button.getAttribute('data-sort');

                    if (state.sort === sort) {
                        state.direction = state.direction === 'asc' ? 'desc' : 'asc';
                    } else {
                        state.sort = sort;
                        state.direction = 'asc';
                    }

                    state.page = 1;
                    render();
                });
            });
            elements.pagination.addEventListener('click', (event) => {
                const button = event.target.closest('[data-page]');

                if (! button || button.disabled) return;

                state.page = Number(button.getAttribute('data-page'));
                render();
            });

            render();
        })();
    </script>
@endsection
