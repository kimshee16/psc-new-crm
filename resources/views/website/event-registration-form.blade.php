<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }}</title>
        @vite('resources/css/app.css')
        <style>
            .psc-event-page {
                color: #24103f;
            }

            .psc-event-header {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 14px;
                margin-bottom: 28px;
                text-align: center;
            }

            .psc-event-logo {
                display: block;
                width: 210px;
                max-width: 72%;
                height: auto;
            }

            .psc-event-heading {
                margin: 0;
                color: #24103f;
                font-size: 28px;
                font-weight: 800;
                line-height: 1.18;
            }

            .psc-event-details {
                margin-top: 16px;
                display: grid;
                gap: 8px;
                color: #475569;
                font-size: 14px;
            }

            .psc-event-form label > span,
            .psc-event-form .psc-field-heading {
                color: #24103f;
            }

            .psc-phone-fields {
                display: grid;
                grid-template-columns: minmax(150px, 190px) minmax(0, 1fr);
                gap: 14px;
            }

            .psc-event-form input:focus,
            .psc-event-form select:focus {
                border-color: #24103f !important;
                box-shadow: 0 0 0 2px rgba(36, 16, 63, 0.15) !important;
                outline: none;
            }

            .psc-event-submit {
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

            .psc-event-submit:hover {
                background: #1a0c2f !important;
            }

            .psc-event-submit:focus {
                outline: none;
                box-shadow: 0 0 0 3px rgba(36, 16, 63, 0.22);
            }

            @media (max-width: 640px) {
                .psc-event-logo {
                    width: 180px;
                    max-width: 78%;
                }

                .psc-event-heading {
                    font-size: 24px;
                }

                .psc-phone-fields {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body class="psc-event-page bg-[#eef6f7] font-sans">
        <main class="mx-auto min-h-screen w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <header class="psc-event-header">
                    <img src="{{ asset('storage/brand/psc-logo.png') }}" alt="Progress Study Consultancy logo" class="psc-event-logo">
                    <div>
                        <h1 class="psc-event-heading">Registration - {{ $event->name }}</h1>
                        <div class="psc-event-details">
                            <p><strong>Date and time:</strong> {{ $event->starts_at->format('l, j F Y g:i A') }} {{ config('app.timezone') }}</p>
                            <p><strong>Location:</strong> {{ $event->location }}</p>
                            <p>Fill in your details to register for this free event.</p>
                        </div>
                    </div>
                </header>

                @if ($errors->any())
                    <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                        Please correct the highlighted fields and submit the form again.
                    </div>
                @endif

                <form method="POST" action="{{ $action }}" class="psc-event-form space-y-5">
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
                            <span class="text-sm font-bold text-slate-700">Last Name</span>
                            <input name="surname" value="{{ old('surname') }}" maxlength="80" pattern="[A-Za-z0-9 ]+" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition">
                            @error('surname') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-700">Email <span class="text-red-600">*</span></span>
                        <input type="email" name="email" value="{{ old('email') }}" required maxlength="120" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition">
                        @error('email') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                    </label>

                    <div>
                        <span class="psc-field-heading block text-sm font-bold text-slate-700">Phone Number <span class="text-red-600">*</span></span>
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
                            <span class="text-sm font-bold text-slate-700">Date of Birth</span>
                            <input type="date" name="dob" value="{{ old('dob') }}" max="{{ now()->toDateString() }}" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition">
                            @error('dob') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Highest education level</span>
                            <select id="highest-education-level" name="highest_education_level" class="mt-1 h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none transition">
                                <option value="">Select education level</option>
                                @foreach ($educationLevels as $educationLevel)
                                    <option value="{{ $educationLevel }}" @selected(old('highest_education_level') === $educationLevel)>{{ $educationLevel }}</option>
                                @endforeach
                            </select>
                            @error('highest_education_level') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <label id="highest-education-other-field" class="block">
                        <span class="text-sm font-bold text-slate-700">Other highest education level</span>
                        <input name="highest_education_level_other" value="{{ old('highest_education_level_other') }}" maxlength="160" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition">
                        @error('highest_education_level_other') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                    </label>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Nationality <span class="text-red-600">*</span></span>
                            <input list="event-countries-{{ $event->id }}" name="nationality" value="{{ old('nationality') }}" required maxlength="80" class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition">
                            <datalist id="event-countries-{{ $event->id }}">
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
                        <span class="text-sm font-bold text-slate-700">How did you hear about our event? <span class="text-red-600">*</span></span>
                        <select name="event_referral_id" required class="mt-1 h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none transition" @disabled($eventReferrals->isEmpty())>
                            <option value="">Select an option</option>
                            @foreach ($eventReferrals as $referral)
                                <option value="{{ $referral->id }}" @selected((string) old('event_referral_id') === (string) $referral->id)>{{ $referral->name }}</option>
                            @endforeach
                        </select>
                        @error('event_referral_id') <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span> @enderror
                    </label>
                    @if ($eventReferrals->isEmpty())
                        <p role="status" class="text-sm font-semibold text-slate-600">Registration is temporarily unavailable. Please try again later.</p>
                    @endif

                    <div class="flex justify-end">
                        <button type="submit" class="psc-event-submit" @disabled($eventReferrals->isEmpty())>Register</button>
                    </div>
                </form>
            </section>
        </main>

        <script>
            const educationSelect = document.getElementById('highest-education-level');
            const otherField = document.getElementById('highest-education-other-field');
            const syncOtherEducationField = () => {
                if (!educationSelect || !otherField) return;
                otherField.hidden = educationSelect.value !== 'Other / Not specified';
            };

            educationSelect?.addEventListener('change', syncOtherEducationField);
            syncOtherEducationField();
        </script>
    </body>
</html>
