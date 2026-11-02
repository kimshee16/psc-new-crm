<div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th scope="col" class="px-3 py-3">Application ID #</th>
                <th scope="col" class="px-3 py-3">Client / Organisation</th>
                <th scope="col" class="px-3 py-3">Application description</th>
                <th scope="col" class="px-3 py-3">Status</th>
                <th scope="col" class="px-3 py-3">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($items as $item)
                <tr>
                    <td class="px-3 py-3"><a href="{{ route('applications.show', $item->id) }}" class="font-semibold text-[#1f7890] underline">#{{ $item->id }}</a></td>
                    <td class="px-3 py-3">{{ $item->client_name }}</td>
                    <td class="px-3 py-3">{{ $item->description }}</td>
                    <td class="px-3 py-3">{{ $item->status }}</td>
                    <td class="px-3 py-3">
                        <form method="POST" action="{{ route('applications.links.update', $application) }}" @if ($action === 'unlink') onsubmit="return confirm('Remove this link from both applications?')" @endif>
                            @csrf
                            <input type="hidden" name="action" value="{{ $action }}">
                            <input type="hidden" name="linked_application_id" value="{{ $item->id }}">
                            <button type="submit" aria-label="{{ ucfirst($action) }} application {{ $item->id }}" class="rounded-md border border-slate-300 px-3 py-2 font-semibold text-[#1f7890]">{{ ucfirst($action) }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-5 text-slate-500">{{ $action === 'unlink' ? 'No linked items available.' : 'No matching applications available to link.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
