@php
    $activeRoute = Route::currentRouteName();
    $navItems = [
        ['route' => 'home', 'label' => 'Home', 'icon' => 'home'],
        ['route' => 'applications', 'label' => 'Applications', 'icon' => 'file'],
        ['route' => 'leads', 'label' => 'Leads', 'icon' => 'reporting'],
        ['route' => 'clients', 'label' => 'Clients', 'icon' => 'clients'],
        ['route' => 'organisations', 'label' => 'Organisations', 'icon' => 'building'],
        ['route' => 'tasks', 'label' => 'Tasks', 'icon' => 'tasks'],
        ['route' => 'rfis', 'label' => 'RFIs', 'icon' => 'rfi'],
    ];
    $managementItems = [
        ['route' => 'my-offices', 'label' => 'My Offices', 'icon' => 'check'],
        ['route' => 'clients', 'label' => 'Clients', 'icon' => null],
        ['route' => 'applications', 'label' => 'Applications', 'icon' => null],
        ['route' => 'leads', 'label' => 'Leads', 'icon' => null],
        ['route' => 'tasks', 'label' => 'Tasks & RFIs', 'icon' => null],
        ['route' => 'reports', 'label' => 'Reports', 'icon' => 'columns'],
        ['route' => 'configuration', 'label' => 'Configuration', 'icon' => 'settings'],
    ];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'PSC CRM' }}</title>
        <link rel="icon" type="image/png" href="{{ asset('storage/brand/psc-favicon.png') }}?v=official-1">
        <link rel="shortcut icon" type="image/png" href="{{ asset('storage/brand/psc-favicon.png') }}?v=official-1">
        <link rel="apple-touch-icon" href="{{ asset('storage/brand/psc-favicon.png') }}?v=official-1">
        @vite('resources/css/app.css')
    </head>
    <body class="bg-[#f3f6f8] font-sans text-[#101820]">
        <div class="min-h-screen">
            <aside class="fixed inset-y-0 left-0 z-20 flex w-[232px] flex-col bg-[#123348] text-white">
                <div class="mx-4 flex h-[82px] flex-col items-start justify-center border-b border-white/10">
                    <img
                        src="{{ asset('storage/brand/psc-logo.png') }}"
                        alt="Progress Study Consultancy"
                        class="block h-auto max-h-11 w-full max-w-[180px] object-contain object-left"
                    >
                    <div class="mt-1 text-xs font-medium text-slate-300">CRM Version 2</div>
                </div>
                <nav class="flex-1 overflow-y-auto px-2 py-6">
                    <div class="px-3 pb-3 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Workspace</div>
                    @foreach ($navItems as $item)
                        @php $isActive = $activeRoute === $item['route']; @endphp
                        <a href="{{ route($item['route']) }}"
                           class="mb-1 flex min-h-9 items-center gap-3 rounded-md px-4 py-2.5 text-sm font-semibold transition {{ $isActive ? 'bg-white/15 text-white' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                            {!! payroll_icon($item['icon'], 'h-4 w-4 shrink-0 text-slate-300') !!}
                            <span class="leading-tight">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                    <div class="px-3 pb-3 pt-5 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Management</div>
                    @foreach ($managementItems as $item)
                        @php $isActive = $activeRoute === $item['route']; @endphp
                        <a href="{{ route($item['route']) }}"
                           class="mb-1 flex min-h-9 items-center gap-3 rounded-md px-4 py-2.5 text-sm font-medium transition {{ $isActive ? 'bg-white/15 text-white' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                            @if ($item['icon'])
                                {!! payroll_icon($item['icon'], 'h-4 w-4 shrink-0 text-slate-300') !!}
                            @else
                                <span class="h-4 w-4 shrink-0"></span>
                            @endif
                            <span class="leading-tight">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
                @auth
                    <div class="px-4 pb-5 text-xs text-slate-300">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="mb-3 flex min-h-9 w-full items-center gap-3 rounded-md px-3 py-2 text-left text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white">
                                {!! payroll_icon('logout', 'h-4 w-4 shrink-0') !!}
                                <span>Sign Out</span>
                            </button>
                        </form>
                        <div>DEV mock-up - illustrative data only</div>
                    </div>
                @endauth
            </aside>
            <main class="min-h-screen pl-[232px]">
                <header class="sticky top-0 z-10 flex h-[66px] items-center justify-between border-b border-slate-200 bg-white px-7">
                    <label class="sr-only" for="global-search">Search</label>
                    <input
                        id="global-search"
                        type="search"
                        placeholder="Search client, application, lead or organisation..."
                        class="h-10 w-full max-w-[540px] rounded-md border border-slate-300 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition placeholder:text-slate-500 focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15"
                    >
                    <div class="ml-6 flex shrink-0 items-center gap-3 text-right">
                        <div>
                            <div class="text-xs text-slate-500">Melbourne Office</div>
                            <div class="text-xs font-bold text-[#101820]">Counsellor</div>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#d8eef4] text-sm font-bold text-[#31556a]">
                            JM
                        </div>
                    </div>
                </header>
                <div class="mx-auto max-w-[1500px] px-7 py-8">
                    @yield('content')
                </div>
            </main>
        </div>
    </body>
</html>
