@if($application->nominee_name || $application->nomineeClient)
    <div class="font-semibold">{{ $application->nominee_name ?: trim(implode(' ', array_filter([$application->nomineeClient->first_name, $application->nomineeClient->middle_name, $application->nomineeClient->surname]))) }}</div>
    @if($application->nomineeClient)
        <a href="{{ route('clients.show', $application->nomineeClient->client_id) }}" class="text-[#15638e] underline">Client {{ $application->nomineeClient->client_id }}</a>
    @endif
@else
    <span class="italic text-slate-500">No nominee linked</span>
@endif
