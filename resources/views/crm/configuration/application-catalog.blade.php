@php
    $hasDocumentTypes = $activeConfigurationTab === 'documents';
    $catalog = $hasDocumentTypes ? 'required_documents' : $activeConfigurationTab;
    $heading = \App\Models\ApplicationCatalogEntry::LABELS[$catalog];
@endphp
<section aria-labelledby="application-catalog-heading">
    <h2 id="application-catalog-heading" class="text-lg font-bold">{{ $heading }}</h2>
    @if ($hasDocumentTypes)
        <details class="my-4" @if($errors->any()) open @endif>
            <summary class="config-action config-primary cursor-pointer">Add new</summary>
            <form method="POST" action="{{ route('configuration.documents.store') }}" class="mt-4 grid max-w-xl gap-3">
                @csrf
                <label class="grid gap-1 text-sm font-semibold">
                    Document type
                    <select name="type" required class="rounded-md border border-slate-300 px-3 py-2">
                        <option value="">Select a document type</option>
                        @foreach (\App\Models\ApplicationCatalogEntry::DOCUMENT_TYPES as $type)
                            <option value="{{ $type }}" @selected(old('type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Document name
                    <input name="name" required maxlength="255" value="{{ old('name') }}" class="rounded-md border border-slate-300 px-3 py-2">
                </label>
                <div><button class="config-action config-primary" type="submit">Save document</button></div>
            </form>
        </details>
    @endif
    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    @if ($hasDocumentTypes)
                        <th scope="col" class="px-4 py-3">Document Type</th>
                    @endif
                    <th scope="col" class="px-4 py-3">{{ match ($catalog) {
                        'actions' => 'Action name',
                        'modules' => 'Module name',
                        default => 'Document Name',
                    } }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($applicationCatalogs->get($catalog, collect()) as $entry)
                    <tr>
                        @if ($hasDocumentTypes)
                            <td class="px-4 py-3">{{ $entry->type }}</td>
                        @endif
                        <td class="px-4 py-3">{{ $entry->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $hasDocumentTypes ? 2 : 1 }}" class="px-4 py-3 text-slate-600">No {{ str($heading)->lower() }} configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
