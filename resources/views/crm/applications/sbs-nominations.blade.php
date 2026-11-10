<p class="mt-2 text-sm text-slate-500">Nominations that reference this SBS. Set the sponsorship on the nomination record.</p>
<div class="mt-4 overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 text-xs text-slate-500"><tr>
            <th scope="col" class="px-3 py-3">Nomination ID</th><th scope="col" class="px-3 py-3">Nominee</th>
            <th scope="col" class="px-3 py-3">Visa subclass - pathway</th><th scope="col" class="px-3 py-3">Status</th>
        </tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($nominations as $nomination)
                <tr>
                    <td class="px-3 py-3"><a href="{{ route('applications.show', $nomination) }}" class="font-semibold text-[#1f7890] underline">{{ $nomination->record_reference }}</a></td>
                    <td class="px-3 py-3">{{ $nomination->nominee_name ?: trim(implode(' ', array_filter([$nomination->nomineeClient?->first_name, $nomination->nomineeClient?->middle_name, $nomination->nomineeClient?->surname]))) ?: 'No nominee linked' }}</td>
                    <td class="px-3 py-3">{{ $nomination->nomination_pathway ?: 'Not set' }}</td>
                    <td class="px-3 py-3">{{ $nomination->status }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-slate-500">No nominations reference this SBS.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $nominations->links() }}</div>
