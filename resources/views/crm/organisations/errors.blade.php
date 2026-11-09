@if ($errors->any())
    <div role="alert" class="my-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <p class="font-semibold">Please correct the following:</p>
        <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
