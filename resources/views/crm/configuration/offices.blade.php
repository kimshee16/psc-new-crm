@php
    $defaultOfficeCode = old('default_office_code', $officeRecords->firstWhere('is_default', true)?->code);
@endphp
<form method="POST" action="{{ route('configuration.offices.update') }}">
    @csrf
    @method('PATCH')
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-bold text-[#101820]">Offices <span class="text-red-600" aria-hidden="true">*</span></h2>
            <button type="submit" class="config-action config-primary">Save Changes</button>
            <a href="{{ route('configuration', ['tab' => 'offices']) }}" class="config-action border border-slate-300 text-slate-700">Remove Changes</a>
        </div>
        <p id="office-default-help" class="px-5 py-4 text-sm text-slate-600">Select one Default Office. It catches all “Other” and unmapped locations for work allocation.</p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-bold">Office Code</th>
                        <th scope="col" class="px-5 py-3 font-bold">Default Office</th>
                        <th scope="col" class="px-5 py-3 text-right font-bold">Office ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($officeRecords as $office)
                        <tr>
                            <th scope="row" class="px-5 py-3 font-medium">{{ $office->code }}</th>
                            <td class="px-5 py-3">
                                <label class="inline-flex cursor-pointer items-center gap-2">
                                    <input type="radio" name="default_office_code" value="{{ $office->code }}" required
                                           aria-label="Make {{ $office->code }} the default office" aria-describedby="office-default-help"
                                           class="office-default-radio h-4 w-4" @checked($defaultOfficeCode === $office->code)>
                                    <span class="office-default-value">{{ $defaultOfficeCode === $office->code ? 'Yes' : 'No' }}</span>
                                </label>
                            </td>
                            <td class="px-5 py-3 text-right">{{ $office->office_id ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">Existing CRM offices without a PSC Office ID are retained for current user assignments.</p>
    </section>

    <details class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" @if ($errors->has('offices.*')) open @endif>
        <summary class="cursor-pointer text-base font-bold text-[#101820]">Office Sender Identity</summary>
        <p class="mt-3 text-sm text-slate-600">Set the sender name and email address for each office. Blank fields use the application's default sender.</p>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th scope="col" class="px-3 py-3">Office Code</th>
                        <th scope="col" class="px-3 py-3">Sender Name</th>
                        <th scope="col" class="px-3 py-3">Sender Email</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($officeRecords as $index => $office)
                        <tr>
                            <th scope="row" class="px-3 py-3 font-medium">
                                {{ $office->code }}
                                <input type="hidden" name="offices[{{ $index }}][code]" value="{{ $office->code }}">
                            </th>
                            <td class="min-w-[220px] px-3 py-3">
                                <input name="offices[{{ $index }}][sender_name]" aria-label="{{ $office->code }} sender name" maxlength="160"
                                       value="{{ old('offices.'.$index.'.sender_name', $office->sender_name) }}" class="config-control">
                            </td>
                            <td class="min-w-[250px] px-3 py-3">
                                <input type="email" name="offices[{{ $index }}][sender_email]" aria-label="{{ $office->code }} sender email" maxlength="255"
                                       value="{{ old('offices.'.$index.'.sender_email', $office->sender_email) }}" class="config-control">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</form>
<style>
    .office-default-value { color: #2563eb; }
    .office-default-radio:checked + .office-default-value { color: #15803d; font-weight: 700; }
</style>
<script>
    document.querySelectorAll('.office-default-radio').forEach((radio) => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.office-default-radio').forEach((input) => {
                input.nextElementSibling.textContent = input.checked ? 'Yes' : 'No';
            });
        });
    });
</script>
