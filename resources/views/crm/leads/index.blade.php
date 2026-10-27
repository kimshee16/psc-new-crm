@extends('layouts.payroll')

@section('content')
    <style>
        .leads-filter-trigger { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; min-height: 40px; margin-top: 8px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; color: #334155; font-size: 14px; font-weight: 500; text-transform: none; letter-spacing: normal; }
        .leads-filter-menu { position: fixed; inset: auto; margin: 0; width: 280px; max-width: calc(100vw - 16px); max-height: calc(100dvh - 16px); overflow-y: auto; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: white; color: #334155; box-shadow: 0 8px 24px #0f172a26; font-size: 13px; font-weight: 400; text-transform: none; letter-spacing: normal; }
        .leads-filter-search { width: 100%; min-width: 0; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
        .leads-filter-actions, .leads-filter-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding-top: 10px; }
        .leads-filter-menu button { color: #1f7890; font-weight: 700; padding: 4px; cursor: pointer; }
        .leads-filter-menu button:focus-visible, .leads-filter-trigger:focus-visible { outline: 2px solid #1f7890; outline-offset: 2px; }
        .leads-filter-options { max-height: 200px; overflow-y: auto; margin-top: 8px; }
        .leads-filter-option { display: flex; align-items: center; gap: 8px; padding: 7px 4px; cursor: pointer; }
        .leads-filter-option[hidden], [data-filter-empty][hidden] { display: none; }
        .leads-filter-option input { accent-color: #1f7890; }
        .leads-filter-footer { border-top: 1px solid #e2e8f0; margin-top: 8px; }
        .leads-filter-footer [data-filter-count] { margin-right: auto; color: #64748b; }
        .leads-filter-footer [data-filter-done] { background: #1f7890; color: white; border-radius: 4px; padding: 6px 10px; }
        .leads-queue-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; padding: 4px 8px; border: 0; border-radius: 999px; box-shadow: none; background: #f1f5f9; color: #475569; font-size: 12px; font-weight: 700; line-height: 16px; white-space: nowrap; }
        .leads-queue-badge[data-level="amber"] { background: #fef3c7; color: #92400e; }
        .leads-queue-badge[data-level="red"] { background: #ffe4e6; color: #9f1239; }
    </style>
    <header class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="text-xs font-bold uppercase tracking-[0.12em] text-[#1f7890]">PSC-10</div>
            <h1 class="mt-2 text-[26px] font-bold leading-tight text-[#101820]">Leads</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a id="leads-export-link" href="{{ route('leads.export', request()->except('page')) }}" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-[#1f7890] hover:text-[#1f7890]">
                {!! payroll_icon('download', 'h-4 w-4') !!}
                <span>Export List</span>
            </a>
            <button id="leads-clear-filter" type="button" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-[#1f7890] hover:text-[#1f7890]">
                {!! payroll_icon('x', 'h-4 w-4') !!}
                <span>Clear Filter</span>
            </button>
        </div>
    </header>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4 text-sm text-slate-600">
            <div id="leads-count">Showing 0 leads</div>
        </div>

        <div class="relative overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.08em] text-slate-500">
                    <tr class="align-top">
                        <th class="min-w-64 px-5 py-3 font-bold">
                            <button type="button" data-sort="client_full_name" class="leads-sort inline-flex items-center gap-1 text-left font-bold uppercase tracking-[0.08em] text-slate-600 hover:text-[#1f7890]">
                                <span>Client</span>
                                <span data-sort-indicator="client_full_name" class="inline-flex h-4 w-4 items-center justify-center" aria-hidden="true"></span>
                                <span data-sort-label="client_full_name" class="sr-only">not sorted</span>
                            </button>
                            <input id="leads-client-name" value="{{ request('client_name') }}" type="search" placeholder="Search client" class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm font-medium normal-case tracking-normal text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                        </th>
                        <th class="min-w-48 px-5 py-3 font-bold">
                            <button type="button" data-sort="status" class="leads-sort inline-flex items-center gap-1 text-left font-bold uppercase tracking-[0.08em] text-slate-600 hover:text-[#1f7890]">
                                <span>Status</span>
                                <span data-sort-indicator="status" class="inline-flex h-4 w-4 items-center justify-center" aria-hidden="true"></span>
                                <span data-sort-label="status" class="sr-only">not sorted</span>
                            </button>
                            <x-leads.filter field="status" label="Status" :options="array_combine($statuses, $statuses)" :selected="$selectedStatus" placeholder="All statuses" />
                        </th>
                        <th class="min-w-40 px-5 py-3 font-bold">
                            <button type="button" data-sort="priority" class="leads-sort inline-flex items-center gap-1 text-left font-bold uppercase tracking-[0.08em] text-slate-600 hover:text-[#1f7890]">
                                <span>Priority</span>
                                <span data-sort-indicator="priority" class="inline-flex h-4 w-4 items-center justify-center" aria-hidden="true"></span>
                                <span data-sort-label="priority" class="sr-only">not sorted</span>
                            </button>
                            <x-leads.filter field="priority" label="Priority" :options="array_combine($priorities, array_map('ucfirst', $priorities))" :selected="$selectedPriority" placeholder="All priorities" />
                        </th>
                        @if ($canViewAdminColumns)
                            <th class="min-w-56 px-5 py-3 font-bold">
                                <button type="button" data-sort="assigned_name" class="leads-sort inline-flex items-center gap-1 text-left font-bold uppercase tracking-[0.08em] text-slate-600 hover:text-[#1f7890]">
                                    <span>Assigned to</span>
                                    <span data-sort-indicator="assigned_name" class="inline-flex h-4 w-4 items-center justify-center" aria-hidden="true"></span>
                                    <span data-sort-label="assigned_name" class="sr-only">not sorted</span>
                                </button>
                            </th>
                            <th class="min-w-52 px-5 py-3 font-bold">
                                <button type="button" data-sort="assigned_office" class="leads-sort inline-flex items-center gap-1 text-left font-bold uppercase tracking-[0.08em] text-slate-600 hover:text-[#1f7890]">
                                    <span>Admin Office</span>
                                    <span data-sort-indicator="assigned_office" class="inline-flex h-4 w-4 items-center justify-center" aria-hidden="true"></span>
                                    <span data-sort-label="assigned_office" class="sr-only">not sorted</span>
                                </button>
                                <x-leads.filter field="office" label="Admin Office" :options="$offices" :selected="$selectedOffice" placeholder="All offices" />
                            </th>
                        @endif
                        <th class="min-w-44 px-5 py-3 font-bold">
                            <button type="button" data-sort="updated_at" class="leads-sort inline-flex items-center gap-1 text-left font-bold uppercase tracking-[0.08em] text-slate-600 hover:text-[#1f7890]">
                                <span>Date last modified</span>
                                <span data-sort-indicator="updated_at" class="inline-flex h-4 w-4 items-center justify-center" aria-hidden="true"></span>
                                <span data-sort-label="updated_at" class="sr-only">sorted descending</span>
                            </button>
                        </th>
                        <th class="min-w-40 px-5 py-3 font-bold">
                            <button type="button" data-sort="days_in_queue" class="leads-sort inline-flex items-center gap-1 text-left font-bold uppercase tracking-[0.08em] text-slate-600 hover:text-[#1f7890]">
                                <span>Days in Queue</span>
                                <span data-sort-indicator="days_in_queue" class="inline-flex h-4 w-4 items-center justify-center" aria-hidden="true"></span>
                                <span data-sort-label="days_in_queue" class="sr-only">not sorted</span>
                            </button>
                        </th>
                        <th class="min-w-32 px-5 py-3 text-right font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody id="leads-table-body" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>

        <div id="leads-empty" class="hidden px-5 py-10 text-center text-sm text-slate-500">
            No leads match the current filters.
        </div>

        <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
            <div id="leads-page-summary" class="text-sm text-slate-600"></div>
            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-sm font-medium text-slate-600">
                    <span>Rows</span>
                    <select id="leads-per-page" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex flex-wrap gap-2" id="leads-pagination"></div>
            </div>
        </div>
    </section>

    <script>
        (() => {
            const leads = @json($leads);
            const routes = {
                export: @json(route('leads.export')),
                show: @json(route('leads.show', ['leadId' => '__LEAD_ID__'])),
            };
            const canViewAdminColumns = @json($canViewAdminColumns);
            const state = {
                client_name: @json(request('client_name', '')),
                status: @json($selectedStatus),
                priority: @json($selectedPriority),
                office: @json($selectedOffice),
                sort: @json($sort),
                direction: @json($direction),
                perPage: @json($perPage),
                page: @json($page),
            };
            const elements = {
                clientName: document.getElementById('leads-client-name'),
                status: document.getElementById('leads-status'),
                priority: document.getElementById('leads-priority'),
                office: document.getElementById('leads-office'),
                perPage: document.getElementById('leads-per-page'),
                tbody: document.getElementById('leads-table-body'),
                empty: document.getElementById('leads-empty'),
                count: document.getElementById('leads-count'),
                pageSummary: document.getElementById('leads-page-summary'),
                pagination: document.getElementById('leads-pagination'),
                exportLink: document.getElementById('leads-export-link'),
                clearFilter: document.getElementById('leads-clear-filter'),
            };
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
                if (status === 'To be contacted') return 'bg-blue-50 text-blue-700';
                if (status === 'Contacted') return 'bg-cyan-50 text-cyan-700';
                if (status === 'Consulting') return 'bg-indigo-50 text-indigo-700';
                if (status === 'Converted') return 'bg-emerald-50 text-emerald-700';
                if (status === 'Withdrawn') return 'bg-slate-100 text-slate-600';

                return 'bg-rose-50 text-rose-700';
            };
            const priorityClasses = (priority) => {
                if (priority === 'high') return 'bg-rose-50 text-rose-700';
                if (priority === 'medium') return 'bg-amber-50 text-amber-700';

                return 'bg-slate-100 text-slate-600';
            };
            const filteredLeads = () => leads
                .filter((lead) => state.client_name === '' || normalise(lead.client_full_name).includes(normalise(state.client_name)))
                .filter((lead) => state.status.length === 0 || state.status.includes(lead.status))
                .filter((lead) => state.priority.length === 0 || state.priority.includes(lead.priority))
                .filter((lead) => !canViewAdminColumns || state.office.length === 0 || state.office.includes(lead.assigned_office_code))
                .sort((left, right) => {
                    const result = ['days_in_queue'].includes(state.sort)
                        ? Number(left[state.sort]) - Number(right[state.sort])
                        : String(left[state.sort] ?? '').localeCompare(String(right[state.sort] ?? ''), undefined, { sensitivity: 'base' });

                    return state.direction === 'desc' ? -result : result;
                });
            const renderRows = (visibleLeads) => {
                elements.tbody.innerHTML = visibleLeads.map((lead) => {
                    const showUrl = routes.show.replace('__LEAD_ID__', encodeURIComponent(lead.lead_id));
                    const clientUrl = lead.client_url ? escapeHtml(lead.client_url) : '#';
                    const duplicateFlag = lead.has_duplicate
                        ? `<span title="Duplicate match" class="inline-flex min-h-9 items-center justify-center rounded-md border border-amber-200 bg-amber-50 px-2 py-2 text-amber-700">{!! payroll_icon('flag', 'h-4 w-4') !!}<span class="sr-only">Duplicate match</span></span>`
                        : '';
                    const adminCells = canViewAdminColumns
                        ? `<td class="px-5 py-4 text-slate-700">${escapeHtml(lead.assigned_name)}</td><td class="px-5 py-4 text-slate-700">${escapeHtml(lead.assigned_office)}</td>`
                        : '';

                    return `
                        <tr>
                            <td class="px-5 py-4">
                                <a href="${clientUrl}" class="font-semibold text-[#2472b8] underline">${escapeHtml(lead.client_full_name)}</a>
                            </td>
                            <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold ${statusClasses(lead.status)}">${escapeHtml(lead.status)}</span></td>
                            <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold capitalize ${priorityClasses(lead.priority)}">${escapeHtml(lead.priority)}</span></td>
                            ${adminCells}
                            <td class="px-5 py-4 text-slate-600">${escapeHtml(lead.modified_at)}</td>
                            <td class="px-5 py-4"><span class="leads-queue-badge" data-level="${escapeHtml(lead.queue_age_level)}">${escapeHtml(lead.days_in_queue_label)}</span></td>
                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex items-center justify-end gap-2">
                                    ${duplicateFlag}
                                    <a href="${showUrl}" title="Open lead" class="inline-flex min-h-9 items-center justify-center rounded-md border border-[#b9dce4] bg-white px-3 py-2 text-sm font-medium text-[#1f7890] transition hover:border-[#1f7890]">
                                        {!! payroll_icon('edit', 'h-4 w-4') !!}
                                        <span class="sr-only">Open lead</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');
            };
            const queryParams = () => {
                const params = new URLSearchParams();

                if (state.client_name) params.set('client_name', state.client_name);
                state.status.forEach((value) => params.append('status[]', value));
                state.priority.forEach((value) => params.append('priority[]', value));
                if (canViewAdminColumns) state.office.forEach((value) => params.append('office[]', value));
                params.set('sort', state.sort);
                params.set('direction', state.direction);
                params.set('per_page', state.perPage);
                if (state.page > 1) params.set('page', state.page);

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
                const results = filteredLeads();
                const totalPages = Math.max(Math.ceil(results.length / state.perPage), 1);

                if (state.page > totalPages) state.page = totalPages;

                const start = results.length === 0 ? 0 : (state.page - 1) * state.perPage + 1;
                const end = Math.min(state.page * state.perPage, results.length);
                const visibleLeads = results.slice(start === 0 ? 0 : start - 1, end);

                renderRows(visibleLeads);
                renderPagination(results.length, totalPages);
                updateSortIndicators();
                syncUrlAndExport();

                elements.empty.classList.toggle('hidden', results.length !== 0);
                elements.count.textContent = results.length === 0 ? 'Showing 0 leads' : `Showing ${start}-${end} of ${results.length} leads`;
                elements.pageSummary.textContent = results.length === 0 ? '' : `Page ${state.page} of ${totalPages}`;
            };
            const updateFilter = (field, value) => {
                state[field] = value;
                state.page = 1;
                render();
            };

            elements.clientName.addEventListener('input', (event) => updateFilter('client_name', event.target.value));
            const filterMenus = [];
            document.querySelectorAll('[data-filter]').forEach((menu) => {
                const field = menu.dataset.filter;
                const trigger = elements[field];
                const search = menu.querySelector('input[type="search"]');
                const options = Array.from(menu.querySelectorAll('.leads-filter-option'));
                const checkboxes = options.map((option) => option.querySelector('input'));
                const count = menu.querySelector('[data-filter-count]');
                const summary = trigger.querySelector('[data-filter-summary]');
                const updateCount = () => count.textContent = `${checkboxes.filter((input) => input.checked).length} of ${checkboxes.length}`;
                const searchOptions = () => {
                    options.forEach((option) => option.hidden = !normalise(option.textContent).includes(normalise(search.value)));
                    menu.querySelector('[data-filter-empty]').hidden = options.some((option) => !option.hidden);
                };
                const sync = () => {
                    checkboxes.forEach((input) => input.checked = state[field].includes(input.value));
                    const selected = options.filter((option) => option.querySelector('input').checked);
                    summary.textContent = selected.length === 0 ? menu.dataset.placeholder
                        : selected.length === 1 ? selected[0].textContent.trim() : `${selected.length} selected`;
                    search.value = '';
                    searchOptions();
                    updateCount();
                };
                const position = () => {
                    const rect = trigger.getBoundingClientRect();
                    menu.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - menu.offsetWidth - 8))}px`;
                    menu.style.top = `${Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - menu.offsetHeight - 8))}px`;
                };
                menu.addEventListener('beforetoggle', (event) => {
                    if (event.newState === 'open') sync();
                    trigger.setAttribute('aria-expanded', String(event.newState === 'open'));
                });
                menu.addEventListener('toggle', (event) => {
                    if (event.newState === 'open') { position(); search.focus(); }
                });
                search.addEventListener('input', searchOptions);
                checkboxes.forEach((input) => input.addEventListener('change', updateCount));
                menu.querySelector('[data-filter-all]').addEventListener('click', () => {
                    options.filter((option) => !option.hidden).forEach((option) => option.querySelector('input').checked = true);
                    updateCount();
                });
                menu.querySelector('[data-filter-clear]').addEventListener('click', () => {
                    checkboxes.forEach((input) => input.checked = false);
                    updateCount();
                });
                menu.querySelector('[data-filter-cancel]').addEventListener('click', () => {
                    menu.hidePopover();
                    trigger.focus();
                });
                menu.querySelector('[data-filter-done]').addEventListener('click', () => {
                    updateFilter(field, checkboxes.filter((input) => input.checked).map((input) => input.value));
                    sync();
                    menu.hidePopover();
                    trigger.focus();
                });
                window.addEventListener('resize', () => { if (menu.matches(':popover-open')) position(); });
                document.addEventListener('scroll', () => { if (menu.matches(':popover-open')) position(); }, true);
                filterMenus.push({ menu, sync });
                sync();
            });
            elements.perPage.addEventListener('change', (event) => {
                state.perPage = Number(event.target.value);
                state.page = 1;
                render();
            });
            document.querySelectorAll('.leads-sort').forEach((button) => {
                button.addEventListener('click', () => {
                    const sort = button.getAttribute('data-sort');

                    if (state.sort === sort) {
                        state.direction = state.direction === 'asc' ? 'desc' : 'asc';
                    } else {
                        state.sort = sort;
                        state.direction = ['updated_at', 'days_in_queue'].includes(sort) ? 'desc' : 'asc';
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
            elements.clearFilter.addEventListener('click', () => {
                state.client_name = '';
                state.status = [];
                state.priority = [];
                state.office = [];
                state.sort = 'updated_at';
                state.direction = 'desc';
                state.perPage = 10;
                state.page = 1;

                elements.clientName.value = '';
                filterMenus.forEach(({ menu, sync }) => {
                    if (menu.matches(':popover-open')) menu.hidePopover();
                    sync();
                });
                elements.perPage.value = '10';

                render();
            });

            render();
        })();
    </script>
@endsection
