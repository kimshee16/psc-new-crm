<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }}</title>
        @vite('resources/css/app.css')
    </head>
    <body class="bg-slate-50 font-sans text-[#101820]">
        <main class="mx-auto min-h-screen w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <header class="mb-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.08em] text-[#1f7890]">Progress Study Consultancy</p>
                    <h1 class="mt-2 text-2xl font-bold leading-tight text-[#101820]">{{ $heading }}</h1>
                </header>

                @if ($errors->any())
                    <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                        Please correct the highlighted fields and submit the form again.
                    </div>
                @endif

                <form method="POST" action="{{ $action }}" class="space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">First Name <span class="text-red-600">*</span></span>
                            <input name="first_name" value="{{ old('first_name') }}" required maxlength="80" pattern="[A-Za-z0-9 ]+" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                            @error('first_name') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Middle Name</span>
                            <input name="middle_name" value="{{ old('middle_name') }}" maxlength="80" pattern="[A-Za-z0-9 ]+" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                            @error('middle_name') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Last Name</span>
                            <input name="surname" value="{{ old('surname') }}" maxlength="80" pattern="[A-Za-z0-9 ]+" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                            @error('surname') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-700">Email <span class="text-red-600">*</span></span>
                        <input type="email" name="email" value="{{ old('email') }}" required maxlength="120" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                        @error('email') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                    </label>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[180px_1fr]">
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Country Code <span class="text-red-600">*</span></span>
                            <select name="phone_country_code" required class="mt-1 h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                                @foreach ($phoneCountryCodes as $code => $label)
                                    <option value="{{ $code }}" @selected(old('phone_country_code', '+61') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('phone_country_code') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Phone Number <span class="text-red-600">*</span></span>
                            <input name="phone_number" value="{{ old('phone_number') }}" required maxlength="40" pattern="[0-9 ()-]{6,24}" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                            @error('phone_number') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Nationality <span class="text-red-600">*</span></span>
                            <input list="{{ $formType }}-countries" name="nationality" value="{{ old('nationality') }}" required maxlength="80" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                            <datalist id="{{ $formType }}-countries">
                                @foreach ($countries as $country)
                                    <option value="{{ $country }}"></option>
                                @endforeach
                            </datalist>
                            @error('nationality') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Current Location <span class="text-red-600">*</span></span>
                            <input list="{{ $formType }}-locations" name="current_location" value="{{ old('current_location') }}" required maxlength="120" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                            <datalist id="{{ $formType }}-locations">
                                @foreach ($locations as $location)
                                    <option value="{{ $location }}"></option>
                                @endforeach
                            </datalist>
                            @error('current_location') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-700">Enquiry</span>
                        <textarea name="enquiry" rows="5" maxlength="4000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">{{ old('enquiry') }}</textarea>
                        @error('enquiry') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                    </label>

                    <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <img id="captcha-image" src="{{ $captchaImageUrl }}" alt="CAPTCHA challenge" class="h-[76px] w-[220px] rounded-md border border-slate-300 bg-white object-cover">
                                <button type="button" id="refresh-captcha" aria-label="Refresh CAPTCHA" title="Refresh CAPTCHA" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-700 transition hover:border-[#1f7890] hover:text-[#1f7890] focus:outline-none focus:ring-2 focus:ring-[#1f7890]/20">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21 12a9 9 0 0 1-15.3 6.4"></path>
                                        <path d="M3 12A9 9 0 0 1 18.3 5.6"></path>
                                        <path d="M18 2v4h-4"></path>
                                        <path d="M6 22v-4h4"></path>
                                    </svg>
                                </button>
                            </div>

                            <label class="block">
                                <span class="text-sm font-bold text-slate-700">CAPTCHA Code <span class="text-red-600">*</span></span>
                                <input name="captcha" required maxlength="6" minlength="6" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm uppercase tracking-[0.14em] outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15">
                                @error('captcha') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="min-h-11 rounded-md bg-[#1f7890] px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#17657a]">Submit</button>
                    </div>
                </form>
            </section>
        </main>

        <script>
            document.getElementById('refresh-captcha')?.addEventListener('click', async () => {
                const response = await fetch('{{ route('website.captcha.refresh') }}', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const data = await response.json();
                document.getElementById('captcha-image').src = data.image_url;
            });
        </script>
    </body>
</html>
