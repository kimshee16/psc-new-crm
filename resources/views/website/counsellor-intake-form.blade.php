<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }}</title>
        @vite('resources/css/app.css')
        <style>
            .psc-intake-page {
                color: #24103f;
            }

            .psc-intake-header {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 14px;
                margin-bottom: 28px;
                text-align: center;
            }

            .psc-intake-logo {
                display: block;
                width: 210px;
                max-width: 72%;
                height: auto;
            }

            .psc-intake-heading {
                margin: 0;
                color: #24103f;
                font-size: 28px;
                font-weight: 800;
                line-height: 1.18;
            }

            .psc-intake-counsellor {
                margin-top: 10px;
                color: #24103f;
                font-size: 16px;
                font-weight: 700;
            }

            .psc-intake-subheading {
                margin-top: 4px;
                color: #475569;
                font-size: 14px;
            }

            .psc-intake-form label > span {
                color: #24103f;
            }

            .psc-phone-fields {
                display: grid;
                grid-template-columns: minmax(150px, 190px) minmax(0, 1fr);
                gap: 14px;
            }

            .psc-intake-form input:focus,
            .psc-intake-form select:focus,
            .psc-intake-form textarea:focus {
                border-color: #24103f !important;
                box-shadow: 0 0 0 2px rgba(36, 16, 63, 0.15) !important;
                outline: none;
            }

            .psc-intake-submit {
                min-height: 44px;
                border-radius: 6px;
                background: #24103f !important;
                color: #ffffff !important;
                padding: 8px 20px;
                font-size: 14px;
                font-weight: 700;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12);
                transition: background-color 150ms ease, transform 150ms ease;
            }

            .psc-intake-submit:hover {
                background: #1a0c2f !important;
            }

            .psc-intake-submit:focus {
                outline: none;
                box-shadow: 0 0 0 3px rgba(36, 16, 63, 0.22);
            }

            @media (max-width: 640px) {
                .psc-intake-logo {
                    width: 180px;
                    max-width: 78%;
                }

                .psc-intake-heading {
                    font-size: 24px;
                }

                .psc-phone-fields {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body class="psc-intake-page bg-[#eef6f7] font-sans">
        <main class="mx-auto min-h-screen w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <header class="psc-intake-header">
                    <img src="{{ asset('storage/brand/psc-logo.png') }}" alt="Progress Study Consultancy logo" class="psc-intake-logo">
                    <div class="psc-intake-header-copy">
                        <h1 class="psc-intake-heading">Welcome to Progress Study Consultancy</h1>
                        <p class="psc-intake-counsellor">Counsellor: {{ $counsellorFirstName }}</p>
                        <p class="psc-intake-subheading">Please fill in your details to get started.</p>
                    </div>
                </header>

                @if ($errors->any())
                    <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                        Please correct the highlighted fields and submit the form again.
                    </div>
                @endif

                <form method="POST" action="{{ $action }}" class="psc-intake-form space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">First Name <span class="text-red-600">*</span></span>
                            <input name="first_name" value="{{ old('first_name') }}" required maxlength="80" pattern="[A-Za-z0-9 ]+" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition">
                            @error('first_name') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Middle Name</span>
                            <input name="middle_name" value="{{ old('middle_name') }}" maxlength="80" pattern="[A-Za-z0-9 ]+" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition">
                            @error('middle_name') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Last Name <span class="text-red-600">*</span></span>
                            <input name="surname" value="{{ old('surname') }}" required maxlength="80" pattern="[A-Za-z0-9 ]+" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition">
                            @error('surname') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-700">Email <span class="text-red-600">*</span></span>
                        <input type="email" name="email" value="{{ old('email') }}" required maxlength="120" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition">
                        @error('email') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                    </label>

                    <div>
                        <span class="block text-sm font-bold text-slate-700">Phone Number <span class="text-red-600">*</span></span>
                        <div class="psc-phone-fields mt-1">
                            <div>
                                <select name="phone_country_code" required aria-label="Country code" class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none transition">
                                    @foreach ($phoneCountryCodes as $code => $label)
                                        <option value="{{ $code }}" @selected(old('phone_country_code', '+61') === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('phone_country_code') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <input name="phone_number" value="{{ old('phone_number') }}" required maxlength="40" pattern="[0-9 ()-]{6,24}" inputmode="tel" aria-label="Phone number" class="h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition">
                                @error('phone_number') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Date of Birth <span class="text-red-600">*</span></span>
                            <input type="date" name="dob" value="{{ old('dob') }}" required max="{{ now()->toDateString() }}" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition">
                            @error('dob') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Highest education level</span>
                            <select name="highest_education_level" class="mt-1 h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none transition">
                                <option value="">Select education level</option>
                                @foreach ($educationLevels as $educationLevel)
                                    <option value="{{ $educationLevel }}" @selected(old('highest_education_level') === $educationLevel)>{{ $educationLevel }}</option>
                                @endforeach
                            </select>
                            @error('highest_education_level') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Nationality <span class="text-red-600">*</span></span>
                            <input list="intake-countries-{{ $counsellor->id }}" name="nationality" value="{{ old('nationality') }}" required maxlength="80" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition">
                            <datalist id="intake-countries-{{ $counsellor->id }}">
                                @foreach ($countries as $country)
                                    <option value="{{ $country }}"></option>
                                @endforeach
                            </datalist>
                            @error('nationality') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Current Location <span class="text-red-600">*</span></span>
                            <select name="current_location" required class="mt-1 h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none transition">
                                <option value="">Select location</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location }}" @selected(old('current_location') === $location)>{{ $location }}</option>
                                @endforeach
                            </select>
                            @error('current_location') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-700">Enquiry</span>
                        <textarea name="enquiry" rows="5" maxlength="4000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none transition">{{ old('enquiry') }}</textarea>
                        @error('enquiry') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                    </label>

                    <div class="flex justify-end">
                        <button type="submit" class="psc-intake-submit">Submit</button>
                    </div>
                </form>
            </section>
        </main>
    </body>
</html>
