<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\MyOfficesController;

$registerRoutes = function () {
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
            'tasks' => 'Tasks',
            'rfis' => 'RFI',
            'reports' => 'Reports',
            'configuration' => 'Configuration',
        ] as $route => $heading) {
            Route::get('/'.$route, fn () => view('crm.section', [
                'title' => 'PSC CRM - '.$heading,
                'activeLabel' => $heading,
                'heading' => $heading,
            ]))->name($route);
        }

        Route::get('/clients', [ClientsController::class, 'index'])->name('clients');
        Route::get('/clients/export', [ClientsController::class, 'export'])->name('clients.export');
        Route::get('/clients/create', [ClientsController::class, 'create'])->name('clients.create');
        Route::get('/clients/search', [ClientsController::class, 'search'])->name('clients.search');
        Route::get('/clients/{clientId}', [ClientsController::class, 'show'])->name('clients.show');

        Route::prefix('my-offices')->name('my-offices.')->group(function () {
            Route::get('/', [MyOfficesController::class, 'index'])->name('index');
            Route::get('/clients', [MyOfficesController::class, 'clients'])->name('clients');
            Route::get('/applications', [MyOfficesController::class, 'applications'])->name('applications');
            Route::get('/leads', [MyOfficesController::class, 'leads'])->name('leads');
            Route::get('/tasks', [MyOfficesController::class, 'tasks'])->name('tasks');
            Route::get('/rfi', [MyOfficesController::class, 'rfi'])->name('rfi');
        });

        Route::redirect('/study-applications', '/applications')->name('study-applications');
        Route::redirect('/rfi', '/rfis')->name('rfi');
        Route::redirect('/reporting', '/reports')->name('reporting');
        Route::redirect('/services', '/applications')->name('services');

        Route::view('/data-upload', 'payroll.data-upload')->name('data-upload');
        Route::view('/master-categories', 'payroll.master-categories')->name('master-categories');
        Route::view('/summary', 'payroll.summary')->name('summary');
        Route::view('/departmental-summary', 'payroll.departmental-summary')->name('departmental-summary');
        Route::view('/journals-export', 'payroll.journals-export')->name('journals-export');
        Route::view('/project-status', 'payroll.project-status')->name('project-status');
    });
};

$basePath = trim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/');

if ($basePath !== '') {
    Route::prefix($basePath)->group($registerRoutes);
} else {
    $registerRoutes();
}
