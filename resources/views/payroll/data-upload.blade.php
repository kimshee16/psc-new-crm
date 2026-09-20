@php
    $title = 'Data Upload & Ingestion';
    $activeLabel = 'Data Upload';
    $uploads = [
        ['name' => 'pivot (25).csv', 'period' => 'Jun 1, 2026 - Jun 30, 2026', 'records' => '14265', 'user' => 'mark@myhavenstores.com', 'date' => '17d ago'],
        ['name' => 'pivot.17.csv', 'period' => 'Apr 1, 2026 - Apr 30, 2026', 'records' => '13048', 'user' => 'kim.ramirezx@myhavenstores.com', 'date' => '24d ago'],
        ['name' => 'pivot.corp17.csv', 'period' => 'Apr 1, 2026 - Apr 30, 2026', 'records' => '2462', 'user' => 'kim.ramirezx@myhavenstores.com', 'date' => '24d ago'],
    ];
@endphp

@extends('layouts.payroll')

@section('content')
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-[#07152f]">Data Upload & Ingestion</h1>
        <p class="mt-2 text-base text-slate-500">Upload raw CSVs or sync data from external payroll providers</p>
    </header>

    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-7 shadow-sm">
        <div class="mb-5 flex items-start justify-between gap-4">
            <h2 class="text-lg font-bold text-[#07152f]">Upload Payroll Data</h2>
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-500">Payroll period</span>
                <select class="w-64 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm text-[#07152f]">
                    <option>4/1/2026 - 4/30/2026 (OPEN)</option>
                    <option>6/1/2026 - 6/30/2026 (APPROVED)</option>
                </select>
            </label>
        </div>
        <div class="mb-5 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            This period has 2 active imports (pivot.17.csv, pivot.corp17.csv). Additional CSV files are merged into the period. Re-uploading the same filename replaces only that file.
        </div>
        <div class="flex min-h-72 flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 text-center">
            {!! payroll_icon('upload', 'h-10 w-10 text-slate-400') !!}
            <div class="mt-4 text-base font-bold text-[#07152f]">Drag and drop your PushOperations CSV here</div>
            <div class="mt-2 text-sm text-slate-500">or</div>
            <button class="mt-4 rounded-lg bg-[#583cea] px-7 py-3 text-sm font-bold text-white shadow-sm">Browse Files</button>
            <p class="mt-5 text-sm text-slate-500">PushOperations CSV only (max ~50MB). Uploading to 4/1/2026 - 4/30/2026.</p>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between px-7 py-5">
            <h2 class="text-lg font-bold text-[#07152f]">Upload History</h2>
            <button class="flex items-center gap-2 text-sm font-semibold text-[#583cea]">{!! payroll_icon('download', 'h-4 w-4') !!} Export Log</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="border-y border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-7 py-3 font-bold">File Name</th>
                        <th class="px-5 py-3 font-bold">Period</th>
                        <th class="px-5 py-3 font-bold">Status</th>
                        <th class="px-5 py-3 font-bold">Records</th>
                        <th class="px-5 py-3 font-bold">Uploaded By</th>
                        <th class="px-5 py-3 font-bold">Uploaded</th>
                        <th class="px-5 py-3 font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($uploads as $upload)
                        <tr>
                            <td class="px-7 py-4 font-bold text-[#07152f]">{{ $upload['name'] }}</td>
                            <td class="px-5 py-4">{{ $upload['period'] }}</td>
                            <td class="px-5 py-4"><span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Validated</span></td>
                            <td class="px-5 py-4">{{ $upload['records'] }}</td>
                            <td class="px-5 py-4">{{ $upload['user'] }}</td>
                            <td class="px-5 py-4">{{ $upload['date'] }}</td>
                            <td class="px-5 py-4">
                                <div class="flex gap-4 text-slate-500">
                                    {!! payroll_icon('eye', 'h-4 w-4') !!}
                                    {!! payroll_icon('edit', 'h-4 w-4') !!}
                                    {!! payroll_icon('trash', 'h-4 w-4') !!}
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
