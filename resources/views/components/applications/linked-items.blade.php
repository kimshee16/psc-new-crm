@props(['application', 'items'])

<section id="linked-items" class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm" aria-labelledby="linked-items-heading">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5">
        <h2 id="linked-items-heading" class="text-lg font-bold">Linked Items</h2>
        <button type="button" data-open-application-links class="rounded-md border border-[#b9dce4] bg-[#eaf7fc] px-4 py-2 text-sm font-semibold text-[#1f5f74] focus-visible:outline-2 focus-visible:outline-[#1f7890]">Link/Unlink Items</button>
    </header>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th scope="col" class="px-5 py-3">Application ID #</th>
                    <th scope="col" class="px-5 py-3">Application description</th>
                    <th scope="col" class="px-5 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    <tr>
                        <td class="px-5 py-3"><a href="{{ route('applications.show', $item->id) }}" class="font-semibold text-[#1f7890] underline" aria-label="Open application {{ $item->id }}">#{{ $item->id }}</a></td>
                        <td class="px-5 py-3">{{ $item->description }}</td>
                        <td class="px-5 py-3">{{ $item->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-6 text-slate-500">No linked items available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
<x-applications.link-dialog :application="$application" />
