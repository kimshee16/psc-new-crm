<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
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

            @media (max-width: 640px) {
                .psc-intake-logo {
                    width: 180px;
                    max-width: 78%;
                }

                .psc-intake-heading {
                    font-size: 24px;
                }
            }
        </style>
    </head>
    <body class="psc-intake-page bg-[#eef6f7] font-sans">
        <main class="mx-auto flex min-h-screen w-full max-w-2xl items-center px-4 py-8 sm:px-6 lg:px-8">
            <section class="w-full rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                <div class="psc-intake-header">
                    <img src="{{ asset('storage/brand/psc-logo.png') }}" alt="Progress Study Consultancy logo" class="psc-intake-logo">
                    <div>
                        <h1 class="psc-intake-heading">Thank you</h1>
                        <p class="mt-3 text-base text-slate-600">Your details have been sent to {{ $counsellorFirstName }}.</p>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
