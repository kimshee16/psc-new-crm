<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return view('crm.home');
    })->name('home');

    foreach ([
        'applications' => 'Applications',
        'leads' => 'Leads',
        'clients' => 'Clients',
        'organisations' => 'Organisations',
        'tasks' => 'Tasks',
        'rfis' => 'RFIs',
        'my-offices' => 'My Offices',
        'reports' => 'Reports',
        'configuration' => 'Configuration',
    ] as $route => $heading) {
        Route::get('/'.$route, fn () => view('crm.section', [
            'title' => 'PSC CRM - '.$heading,
            'activeLabel' => $heading,
            'heading' => $heading,
        ]))->name($route);
    }

    Route::redirect('/study-applications', '/applications')->name('study-applications');
    Route::redirect('/services', '/applications')->name('services');
    Route::redirect('/rfi', '/rfis')->name('rfi');
    Route::redirect('/reporting', '/reports')->name('reporting');

    Route::view('/data-upload', 'payroll.data-upload')->name('data-upload');
    Route::view('/master-categories', 'payroll.master-categories')->name('master-categories');
    Route::view('/summary', 'payroll.summary')->name('summary');
    Route::view('/departmental-summary', 'payroll.departmental-summary')->name('departmental-summary');
    Route::view('/journals-export', 'payroll.journals-export')->name('journals-export');
    Route::view('/project-status', 'payroll.project-status')->name('project-status');
});
