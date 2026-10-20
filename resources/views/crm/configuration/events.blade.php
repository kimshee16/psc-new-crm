<section class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-base font-bold text-[#101820]">{{ $showEventForm ? ($editingEvent ? 'Edit PSC Event' : 'Add PSC Event') : 'PSC Events' }}</h2>
                <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800">WIP</span>
            </div>
            <p class="mt-2 text-sm text-slate-600">Configure event details and registration links. Further event requirements are still being finalised.</p>
        </div>
        @unless ($showEventForm)
            <a href="{{ route('configuration', ['tab' => 'events', 'action' => 'create']) }}" class="config-action config-primary">Add PSC Event</a>
        @endunless
    </div>

    @if ($showEventForm)
        <form method="POST" action="{{ $editingEvent ? route('configuration.events.update', $editingEvent) : route('configuration.events.store') }}" class="p-5">
            @csrf
            @if ($editingEvent) @method('PATCH') @endif
            <div class="mb-5 text-sm text-slate-600">
                <span class="font-bold">Event ID:</span> {{ $editingEvent?->id ?? 'Assigned automatically when saved' }}
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Event Name</span>
                    <input name="name" value="{{ old('name', $editingEvent?->name) }}" required maxlength="160" class="config-control mt-1 @error('name') is-invalid @enderror">
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Date and Time ({{ config('app.timezone') }})</span>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $editingEvent?->starts_at?->format('Y-m-d\TH:i')) }}" required class="config-control mt-1 @error('starts_at') is-invalid @enderror">
                </label>
                <label class="block lg:col-span-2">
                    <span class="text-xs font-bold text-slate-600">Location</span>
                    <input name="location" value="{{ old('location', $editingEvent?->location) }}" required maxlength="180" class="config-control mt-1 @error('location') is-invalid @enderror">
                </label>
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $editingEvent?->active ?? false))>
                    <span>Active — accept registrations</span>
                </label>
            </div>
            <p class="mt-4 text-xs text-slate-500">Inactive events keep their existing lead history and registration URL, but the public form is unavailable. Dates use {{ config('app.timezone') }} time.</p>
            @if ($editingEvent)
                <label class="mt-5 block">
                    <span class="text-xs font-bold text-slate-600">Registration Form Link</span>
                    <input type="url" readonly value="{{ $editingEvent->registrationUrl() }}" onclick="this.select()" class="config-control mt-1">
                </label>
                <p class="mt-2 text-xs text-slate-500">The event ID and link stay the same when details change. Existing leads retain the event name recorded at the time.</p>
            @endif
            <div class="mt-5 flex gap-2">
                <button type="submit" class="config-action config-primary">Save Event</button>
                <a href="{{ route('configuration', ['tab' => 'events']) }}" class="config-action config-primary">Cancel</a>
            </div>
        </form>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Event ID / Name</th>
                        <th scope="col" class="px-5 py-3">Date / Location</th>
                        <th scope="col" class="px-5 py-3">Registration Form Link</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($events as $event)
                        <tr>
                            <td class="px-5 py-4">
                                <div class="text-xs text-slate-500">Event ID {{ $event->id }}</div>
                                <div class="mt-1 font-semibold">{{ $event->name }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="whitespace-nowrap">{{ $event->starts_at->format('d M Y, H:i') }} {{ config('app.timezone') }}</div>
                                <div class="mt-1 text-slate-500">{{ $event->location }}</div>
                            </td>
                            <td class="min-w-52 px-5 py-4">
                                <label class="sr-only" for="event-link-{{ $event->id }}">Registration link for event {{ $event->id }}</label>
                                <input id="event-link-{{ $event->id }}" type="url" readonly value="{{ $event->registrationUrl() }}" onclick="this.select()" class="config-control">
                                @if ($event->active)
                                    <a href="{{ $event->registrationUrl() }}" target="_blank" rel="noopener" class="mt-2 inline-block text-xs font-bold text-[#1f7890] hover:underline">Open registration form</a>
                                @else
                                    <span class="mt-2 block text-xs text-slate-500">Registration closed</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">{{ $event->active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('configuration', ['tab' => 'events', 'edit' => $event->id]) }}" class="config-action config-primary" aria-label="Edit event {{ $event->id }}">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No PSC events configured. Add an event to generate its registration form link.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($events->hasPages())
            <div class="border-t border-slate-200 p-5">{{ $events->links() }}</div>
        @endif
    @endif
</section>
