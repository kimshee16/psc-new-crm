<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\CounsellorIntakeFormController;
use App\Http\Controllers\CountryConfigurationController;
use App\Http\Controllers\EventConfigurationController;
use App\Http\Controllers\EventRegistrationFormController;
use App\Http\Controllers\HolidaysController;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\MyOfficesController;
use App\Http\Controllers\UserConfigurationController;
use App\Http\Controllers\UserInvitationController;
use App\Http\Controllers\WebsiteLeadFormController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

$registerRoutes = function () {
    Route::get('/invitation/{token}', [UserInvitationController::class, 'show'])->name('invitation.accept');
    Route::post('/invitation', [UserInvitationController::class, 'accept'])->middleware('throttle:6,1')->name('invitation.store');
    Route::get('/run-migration', function () {
        Artisan::call('migrate:refresh');

        return 'Database migrated successfully!';
    });

    Route::get('/run-seeder', function () {
        Artisan::call('db:seed', ['--force' => true]);

        return implode(PHP_EOL, [
            'Database seeded successfully!',
            'Admin email: admin@psccrm.test',
            'Admin password: password',
        ]);
    });

    Route::get('/contact', [WebsiteLeadFormController::class, 'contact'])->name('website.contact');
    Route::post('/contact', [WebsiteLeadFormController::class, 'submitContact'])->name('website.contact.submit');
    Route::get('/book-a-free-consultation', [WebsiteLeadFormController::class, 'consultation'])->name('website.consultation');
    Route::post('/book-a-free-consultation', [WebsiteLeadFormController::class, 'submitConsultation'])->name('website.consultation.submit');
    Route::get('/website/captcha/image', [WebsiteLeadFormController::class, 'captchaImage'])->name('website.captcha.image');
    Route::get('/website/captcha/refresh', [WebsiteLeadFormController::class, 'refreshCaptcha'])->name('website.captcha.refresh');
    Route::get('/intake/{counsellor}', [CounsellorIntakeFormController::class, 'show'])->name('counsellor-intake.show');
    Route::post('/intake/{counsellor}', [CounsellorIntakeFormController::class, 'submit'])->name('counsellor-intake.submit');
    Route::get('/intake/{counsellor}/thanks', [CounsellorIntakeFormController::class, 'thanks'])->name('counsellor-intake.thanks');
    Route::get('/events/{event}/register', [EventRegistrationFormController::class, 'show'])->name('event-registration.show');
    Route::post('/events/{event}/register', [EventRegistrationFormController::class, 'submit'])->name('event-registration.submit');
    Route::get('/events/{event}/register/thanks', [EventRegistrationFormController::class, 'thanks'])->name('event-registration.thanks');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    });

    Route::post('/logout', [AuthController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    Route::middleware(['auth', 'active-user'])->group(function () {
        Route::get('/', function () {
            return view('crm.home');
        })->name('home');

        foreach ([
            'applications' => 'Applications',
            'tasks' => 'Tasks',
            'rfis' => 'RFI',
            'reports' => 'Reports',
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
        Route::post('/clients', [ClientsController::class, 'store'])->name('clients.store');
        Route::get('/clients/search', [ClientsController::class, 'search'])->name('clients.search');
        Route::post('/clients/{clientId}/applications', [ClientsController::class, 'createApplication'])->name('clients.applications.create');
        Route::get('/clients/{clientId}', [ClientsController::class, 'show'])->name('clients.show');
        Route::get('/clients/{clientId}/passport-photo', [ClientsController::class, 'passportPhoto'])->name('clients.passport-photo');
        Route::post('/clients/{clientId}/edit', [ClientsController::class, 'edit'])->name('clients.edit');
        Route::patch('/clients/{clientId}', [ClientsController::class, 'update'])->name('clients.update');
        Route::post('/clients/{clientId}/discard', [ClientsController::class, 'discard'])->name('clients.discard');

        Route::get('/leads', [LeadsController::class, 'index'])->name('leads');
        Route::get('/leads/export', [LeadsController::class, 'export'])->name('leads.export');
        Route::patch('/leads/{leadId}', [LeadsController::class, 'update'])->name('leads.update');
        Route::get('/leads/{leadId}', [LeadsController::class, 'show'])->name('leads.show');
        Route::get('/leads/{leadId}/edit', [LeadsController::class, 'edit'])->name('leads.edit');

        Route::get('/holidays', [HolidaysController::class, 'index'])->name('holidays');
        Route::get('/holidays/create', [HolidaysController::class, 'create'])->name('holidays.create');
        Route::post('/holidays', [HolidaysController::class, 'store'])->name('holidays.store');
        Route::get('/holidays/{holiday}/edit', [HolidaysController::class, 'edit'])->name('holidays.edit');
        Route::patch('/holidays/{holiday}', [HolidaysController::class, 'update'])->name('holidays.update');
        Route::patch('/holidays/{holiday}/cancel', [HolidaysController::class, 'cancel'])->name('holidays.cancel');

        Route::get('/configuration', [ConfigurationController::class, 'index'])->name('configuration');
        Route::post('/configuration/events', [EventConfigurationController::class, 'store'])->name('configuration.events.store');
        Route::patch('/configuration/events/{event}', [EventConfigurationController::class, 'update'])->name('configuration.events.update');
        Route::post('/configuration/countries', [CountryConfigurationController::class, 'store'])->name('configuration.countries.store');
        Route::patch('/configuration/countries/{country}', [CountryConfigurationController::class, 'update'])->name('configuration.countries.update');
        Route::delete('/configuration/countries/{country}', [CountryConfigurationController::class, 'destroy'])->name('configuration.countries.destroy');
        Route::post('/configuration/redirections', [ConfigurationController::class, 'storeRedirection'])->name('configuration.redirections.store');
        Route::patch('/configuration/redirections/{redirection}', [ConfigurationController::class, 'updateRedirection'])->name('configuration.redirections.update');
        Route::delete('/configuration/redirections/{redirection}', [ConfigurationController::class, 'destroyRedirection'])->name('configuration.redirections.destroy');
        Route::post('/configuration/users', [UserConfigurationController::class, 'store'])->name('configuration.users.store');
        Route::patch('/configuration/users', [UserConfigurationController::class, 'updateMany'])->name('configuration.users.save');
        Route::patch('/configuration/users/{user}', [UserConfigurationController::class, 'update'])->name('configuration.users.update');
        Route::get('/configuration/users/{user}/picture', [UserConfigurationController::class, 'picture'])->name('configuration.users.picture');
        Route::post('/configuration/regions', [ConfigurationController::class, 'storeRegion'])->name('configuration.regions.store');
        Route::patch('/configuration/regions/{region}', [ConfigurationController::class, 'updateRegion'])->name('configuration.regions.update');
        Route::patch('/configuration/offices', [ConfigurationController::class, 'updateOffices'])->name('configuration.offices.update');
        Route::post('/configuration/locations', [ConfigurationController::class, 'storeLocation'])->name('configuration.locations.store');
        Route::patch('/configuration/locations/{location}', [ConfigurationController::class, 'updateLocation'])->name('configuration.locations.update');
        Route::delete('/configuration/locations/{location}', [ConfigurationController::class, 'destroyLocation'])->name('configuration.locations.destroy');
        Route::post('/configuration/education-levels', [ConfigurationController::class, 'storeEducationLevel'])->name('configuration.education-levels.store');
        Route::patch('/configuration/education-levels/{educationLevel}', [ConfigurationController::class, 'updateEducationLevel'])->name('configuration.education-levels.update');
        Route::delete('/configuration/education-levels/{educationLevel}', [ConfigurationController::class, 'destroyEducationLevel'])->name('configuration.education-levels.destroy');

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
