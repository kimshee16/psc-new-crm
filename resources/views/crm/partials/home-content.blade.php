@php
    $stats = [
        ['label' => 'Open Applications', 'value' => '38', 'link' => 'View Applications', 'route' => 'applications', 'badge' => 'A'],
        ['label' => 'Open Leads', 'value' => '14', 'link' => 'View Leads', 'route' => 'leads', 'badge' => 'L'],
        ['label' => 'Open Tasks', 'value' => '21', 'link' => 'View Tasks', 'route' => 'tasks', 'badge' => 'T'],
        ['label' => 'Open RFIs', 'value' => '7', 'link' => 'View RFIs', 'route' => 'rfis', 'badge' => 'R'],
    ];

    $dates = [
        ['date' => '09 Sep 2026', 'tone' => 'red', 'name' => 'Ana Santos', 'meta' => 'Student Visa 500 - APP-10482', 'due' => 'Lapsed', 'status' => 'Preparing'],
        ['date' => '24 Sep 2026', 'tone' => 'red', 'name' => 'Daniel Park', 'meta' => 'Visa 485 - APP-10511', 'due' => '1.3 weeks', 'status' => 'Documents'],
        ['date' => '14 Oct 2026', 'tone' => 'amber', 'name' => 'Maria Cruz', 'meta' => 'Study Application - APP-10398', 'due' => '4.1 weeks', 'status' => 'Preparing'],
        ['date' => '03 Nov 2026', 'tone' => 'green', 'name' => 'Ravi Kumar', 'meta' => 'Study Application - APP-10544', 'due' => '7 weeks', 'status' => 'Review'],
    ];

    $notifications = [
        ['time' => 'Today 10:42', 'unread' => true, 'body' => 'Michelle Lee uploaded a document to Ana Santos - Student Visa 500.'],
        ['time' => 'Today 09:18', 'unread' => true, 'body' => 'You have been assigned as Primary Counsellor for APP-10566 - Diploma of Nursing.'],
        ['time' => 'Yesterday 16:05', 'unread' => false, 'body' => 'Daniel Park completed the Visa Information Form.'],
        ['time' => '12 Sep 13:20', 'unread' => true, 'body' => 'Task Review supporting documents is due tomorrow.'],
        ['time' => '11 Sep 11:44', 'unread' => false, 'body' => 'Payment recorded against APP-10398 - Maria Cruz.'],
    ];
@endphp

<header class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <h1 class="text-[26px] font-bold leading-tight text-[#101820]">Welcome back, Jessica Morgan</h1>
        <p class="mt-2 text-sm text-slate-600">Here is your current workload and the items that need attention.</p>
        <div class="mt-4 flex items-center gap-2 text-xs text-slate-600">
            <span>View:</span>
            <span class="rounded-full bg-[#e8f3ef] px-3 py-1 text-[#1f6b61]">My work</span>
            <span class="rounded-full bg-[#e8f3ef] px-3 py-1 text-[#1f6b61]">Melbourne Office</span>
        </div>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('clients') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-[#1f7890] hover:text-[#1f7890]">Search Client</a>
        <a href="{{ route('clients') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-[#1f7890] hover:text-[#1f7890]">Create Client</a>
        <a href="{{ route('leads') }}" class="rounded-md bg-[#1f7890] px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#17657a]">Create Lead</a>
    </div>
</header>

<section class="mb-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach ($stats as $stat)
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm text-slate-600">{{ $stat['label'] }}</div>
                    <div class="mt-6 text-3xl font-bold leading-none text-[#101820]">{{ $stat['value'] }}</div>
                    <a href="{{ route($stat['route']) }}" class="mt-4 inline-flex text-sm font-medium text-[#1f7890]">{{ $stat['link'] }} &rarr;</a>
                </div>
                <span class="flex h-9 w-9 items-center justify-center rounded-md bg-[#eef8fa] text-sm font-bold text-[#1f7890]">{{ $stat['badge'] }}</span>
            </div>
        </article>
    @endforeach
</section>

<section class="grid grid-cols-1 gap-5 xl:grid-cols-[1.6fr_1.05fr]">
    <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-base font-bold text-[#101820]">Upcoming Dates</h2>
                <p class="mt-1 text-xs text-slate-500">6 applications to be lodged in the next 2 months</p>
            </div>
            <a href="{{ route('applications') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-[#1f7890] hover:text-[#1f7890]">View all</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-bold">Critical Date</th>
                        <th class="px-5 py-3 font-bold">Application</th>
                        <th class="px-5 py-3 font-bold">Due</th>
                        <th class="px-5 py-3 font-bold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($dates as $item)
                        <tr>
                            <td class="px-5 py-4">
                                <span class="rounded-md px-2 py-1 text-xs font-bold {{ $item['tone'] === 'red' ? 'bg-red-50 text-red-700' : ($item['tone'] === 'amber' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') }}">{{ $item['date'] }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-bold text-[#101820]">{{ $item['name'] }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $item['meta'] }}</div>
                            </td>
                            <td class="px-5 py-4 font-semibold {{ $item['due'] === 'Lapsed' ? 'text-red-700' : 'text-slate-700' }}">{{ $item['due'] }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ $item['status'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 text-xs text-slate-500">
            <span class="font-bold text-[#101820]">Jira rule reflected:</span> red/amber urgency changes by application type, lapsed dates appear first, and the module covers the next two months.
        </div>
    </article>

    <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-base font-bold text-[#101820]">Workflow Notifications</h2>
                <p class="mt-1 text-xs text-slate-500">Recent alerts relating to your cases and assigned work</p>
            </div>
            <span class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">Sample messages only</span>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach ($notifications as $note)
                <div class="grid grid-cols-[14px_74px_1fr] gap-2 px-5 py-4 text-sm">
                    <span class="mt-1 h-2 w-2 rounded-full {{ $note['unread'] ? 'bg-blue-600' : 'bg-slate-300' }}"></span>
                    <span class="text-xs leading-snug text-slate-500">{{ $note['time'] }}</span>
                    <span class="leading-relaxed text-[#101820]">{{ $note['body'] }}</span>
                </div>
            @endforeach
        </div>
        <div class="px-5 py-4 text-xs leading-relaxed text-slate-500">
            Unread items use the blue indicator. Notifications are shown for the configured retention period; the original requirement currently states 3 weeks.
        </div>
    </article>
</section>

<section class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-bold text-[#101820]">Lead Pipeline</h2>
        <div class="mt-4 grid grid-cols-3 gap-3 text-center">
            <div class="rounded-md bg-slate-50 p-4">
                <div class="text-2xl font-bold">6</div>
                <div class="mt-1 text-xs text-slate-500">New</div>
            </div>
            <div class="rounded-md bg-slate-50 p-4">
                <div class="text-2xl font-bold">5</div>
                <div class="mt-1 text-xs text-slate-500">Contacted</div>
            </div>
            <div class="rounded-md bg-slate-50 p-4">
                <div class="text-2xl font-bold">3</div>
                <div class="mt-1 text-xs text-slate-500">Qualified</div>
            </div>
        </div>
    </article>
    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-bold text-[#101820]">Office Workload</h2>
        <div class="mt-4 space-y-3">
            @foreach ([['Melbourne', 76], ['Sydney', 52], ['Brisbane', 34]] as [$office, $value])
                <div>
                    <div class="mb-1 flex justify-between text-xs font-semibold text-slate-600">
                        <span>{{ $office }}</span>
                        <span>{{ $value }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-[#1f7890]" style="width: {{ $value }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </article>
</section>
