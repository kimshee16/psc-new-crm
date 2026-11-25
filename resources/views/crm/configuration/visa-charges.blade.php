@php
    $chargeAmounts = \App\Http\Controllers\VisaChargeConfigurationController::AMOUNTS;
    $mayEditCharges = in_array(auth()->user()?->account_type, [\App\Models\User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true);
@endphp
<style>[x-cloak] { display: none !important; }</style>

<section class="rounded-lg border border-slate-200 bg-white p-4"
    x-data="visaChargeEditor(@js($visaChargeGroups), @js(array_keys($chargeAmounts)), @js(today()->toDateString()), @js(route('configuration.visa-charges.update')), @js(csrf_token()))">
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <label class="flex items-center gap-2 text-sm" x-show="!editing">
            <input type="checkbox" role="switch" x-model="currentOnly">
            Only show current prices
        </label>
        @if ($mayEditCharges && count($visaChargeGroups))
            <button type="button" class="rounded border px-3 py-1" x-show="!editing" @click="editing = true">Edit</button>
            <template x-if="editing">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="rounded border px-3 py-1" @click="bulkOpen = !bulkOpen">Bulk Gov Update</button>
                    <button type="button" class="rounded bg-slate-800 px-3 py-1 text-white" :disabled="saving || !!validationError()" @click="save()">Save changes</button>
                    <button type="button" class="rounded border px-3 py-1" :disabled="saving" @click="cancel()">Cancel</button>
                </div>
            </template>
        @endif
    </div>
    <form x-show="editing && bulkOpen" x-cloak class="mb-4 flex flex-wrap items-end gap-2" @submit.prevent="bulkChange()">
        <label class="grid gap-1 text-sm">
            New effective date
            <input type="date" required x-model="bulkDate" class="rounded border px-2 py-1">
        </label>
        <button type="submit" class="rounded border px-3 py-1">Create new rows</button>
    </form>
    <p role="alert" class="mb-3 text-sm text-red-700" x-show="error || validationError()" x-text="error || validationError()"></p>
    <p class="mb-3 text-sm font-semibold" x-show="bulkEffective" x-text="'New prices effective ' + dateLabel(bulkEffective)"></p>
    @if (!count($visaChargeGroups))
        <p class="text-sm text-slate-600">Create a visa subtype in Application Types to configure its charges.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th scope="col" class="p-2">Visa subclass</th>
                        @foreach ($chargeAmounts as $label)
                            <th scope="col" class="p-2">{{ $label }}</th>
                        @endforeach
                        <th scope="col" class="p-2">2nd instalment description</th>
                        <th scope="col" class="p-2">Effective date</th>
                        <th scope="col" class="p-2" x-show="editing">Actions</th>
                    </tr>
                </thead>
                <template x-for="group in groups" :key="group.id">
                    <tbody class="border-b border-slate-200">
                        <tr class="bg-slate-50">
                            <th class="p-2" colspan="8" scope="rowgroup" x-text="group.name"></th>
                            <td class="p-2" x-show="editing">
                                <button type="button" class="underline whitespace-nowrap" @click="addVariation(group)">Add Variation</button>
                            </td>
                        </tr>
                        <template x-for="row in visibleRows(group)" :key="row.key">
                            <tr class="border-t border-slate-100" :class="row.bulk ? 'bg-blue-50' : ''">
                                <td class="p-2">
                                    <template x-if="editing && variations(group) && !row.locked">
                                        <input type="text" maxlength="255" aria-label="Variation name" placeholder="Variation name" class="w-40 rounded border p-1" x-model="row.name">
                                    </template>
                                    <span x-show="!editing || !variations(group) || row.locked" x-text="row.name || '—'"></span>
                                </td>
                                @foreach ($chargeAmounts as $field => $label)
                                    <td class="p-2 whitespace-nowrap">
                                        <template x-if="editing">
                                            <input type="number" min="0" max="999999999.99" step="0.01" aria-label="{{ $label }}" class="w-24 rounded border p-1" x-model="row.{{ $field }}">
                                        </template>
                                        <span x-show="!editing" x-text="money(row.{{ $field }})"></span>
                                    </td>
                                @endforeach
                                <td class="p-2">
                                    <template x-if="editing">
                                        <input type="text" maxlength="2000" aria-label="2nd instalment description" class="rounded border p-1" x-model="row.description">
                                    </template>
                                    <span x-show="!editing" x-text="row.description || '—'"></span>
                                </td>
                                <td class="p-2 whitespace-nowrap">
                                    <template x-if="editing">
                                        <input type="date" aria-label="Effective date" class="rounded border p-1" :class="needsDate(row) && !row.effective ? 'border-yellow-600 bg-yellow-50' : ''" x-model="row.effective">
                                    </template>
                                    <span x-show="!editing" x-text="dateLabel(row.effective)"></span>
                                </td>
                                <td class="p-2 whitespace-nowrap" x-show="editing">
                                    <button type="button" class="underline" x-show="!row.locked && !row.bulk" @click="change(group, row)">Gov change</button>
                                    <button type="button" class="ml-2 text-red-700 underline" x-show="variations(group) && group.rows.length > 1" @click="remove(group, row)">Delete</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </template>
            </table>
        </div>
    @endif
</section>

@vite('resources/js/visa-charge-editor.js')
