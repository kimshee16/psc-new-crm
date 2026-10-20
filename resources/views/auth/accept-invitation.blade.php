<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Set Password - PSC CRM</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900">
    <main class="mx-auto mt-16 max-w-md rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-xl font-bold">Welcome to PSC CRM</h1>
        <p class="mt-2 text-sm text-slate-600">Set your password to get started. Use at least 12 characters, including letters and numbers.</p>
        @if ($errors->any())
            <ul class="my-4 text-sm text-red-700" role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif
        <form method="POST" action="{{ route('invitation.store') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="block text-sm">Email<input type="email" name="email" required autocomplete="email" value="{{ old('email', $email) }}" class="mt-1 block w-full rounded border border-slate-300 p-2"></label>
            <label class="block text-sm">Password<input type="password" name="password" required minlength="12" autocomplete="new-password" class="mt-1 block w-full rounded border border-slate-300 p-2"></label>
            <label class="block text-sm">Confirm Password<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" class="mt-1 block w-full rounded border border-slate-300 p-2"></label>
            <button class="w-full rounded bg-[#5035ff] px-4 py-2 text-white" type="submit">Set Password</button>
        </form>
    </main>
</body>
</html>
