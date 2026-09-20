<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Login - PSC CRM</title>
        <link rel="icon" type="image/png" href="{{ asset('storage/brand/psc-favicon.png') }}?v=official-1">
        <link rel="shortcut icon" type="image/png" href="{{ asset('storage/brand/psc-favicon.png') }}?v=official-1">
        <link rel="apple-touch-icon" href="{{ asset('storage/brand/psc-favicon.png') }}?v=official-1">
        @vite('resources/css/app.css')
    </head>
    <body class="min-h-screen bg-[#f1f3f5] font-sans text-[#111827]">
        <main class="flex min-h-screen items-center justify-center px-6 py-10">
            <section class="w-full max-w-[390px] rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                <header class="mb-7">
                    <img
                        src="{{ asset('storage/brand/psc-logo.png') }}"
                        alt="Progress Study Consultancy"
                        class="mb-5 block h-auto max-h-16 w-full max-w-[300px] object-contain object-left"
                    >
                    <p class="mt-2 text-sm text-slate-500">Sign in to continue.</p>
                </header>

                @if (session('status'))
                    <div class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            autofocus
                            required
                            class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-[#003b5c] focus:ring-2 focus:ring-[#003b5c]/15"
                        >
                        @error('email')
                            <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-[#003b5c] focus:ring-2 focus:ring-[#003b5c]/15"
                        >
                        @error('password')
                            <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm font-medium text-slate-600">
                        <input
                            name="remember"
                            type="checkbox"
                            value="1"
                            class="h-4 w-4 rounded border-slate-300 text-[#003b5c] focus:ring-[#003b5c]"
                        >
                        Remember me
                    </label>

                    <button type="submit" class="flex w-full items-center justify-center rounded-md bg-[#003b5c] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#005078] focus:outline-none focus:ring-2 focus:ring-[#003b5c]/25">
                        Sign In
                    </button>
                </form>
            </section>
        </main>
    </body>
</html>
