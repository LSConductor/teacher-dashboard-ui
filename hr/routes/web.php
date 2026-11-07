<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CasualController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\InductionController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StartController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check() && auth()->user()->role !== 'casual') {
        return redirect()->route('casuals.index');
    }

    return app(StartController::class)->show(request(), app(\App\Services\ClearToTeach::class));
});
Route::get('/start', [StartController::class, 'show'])->name('start');
Route::get('/casual-induction', function () {
    return redirect()->route('start', request()->query());
});
Route::get('/apply', [ApplicationController::class, 'create'])->name('apply');
Route::post('/apply', [ApplicationController::class, 'store'])->name('apply.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);
    Route::get('/invites/{token}', [InviteController::class, 'accept'])->name('invites.accept');
    Route::post('/invites/{token}', [InviteController::class, 'complete']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/people', [PeopleController::class, 'index'])->name('people');
    Route::get('/people/{member}/photo', [PeopleController::class, 'photo'])->name('people.photo');
    Route::get('/casuals', [CasualController::class, 'index'])->name('casuals.index');
    Route::get('/casuals/new', [CasualController::class, 'create'])->name('casuals.create')->middleware('role:hr,conductor');
    Route::post('/casuals', [CasualController::class, 'store'])->name('casuals.store')->middleware('role:hr,conductor');
    Route::get('/casuals/{person}/induction', [InductionController::class, 'pack'])->name('casuals.pack');
    Route::get('/casuals/{person}/induction/{module}', [InductionController::class, 'show'])->name('casuals.induction');
    Route::get('/casuals/{person}/forms', [FormController::class, 'index'])->name('casuals.forms');
    Route::get('/casuals/{person}/forms/personal', [FormController::class, 'personal'])->name('casuals.forms.personal');
    Route::post('/casuals/{person}/forms/personal', [FormController::class, 'savePersonal']);
    Route::get('/casuals/{person}/forms/accreditation', [FormController::class, 'accreditation'])->name('casuals.forms.accreditation');
    Route::get('/casuals/{person}/forms/wwcc', [FormController::class, 'wwcc'])->name('casuals.forms.wwcc');
    Route::get('/casuals/{person}/forms/availability', [FormController::class, 'availability'])->name('casuals.forms.availability');
    Route::post('/casuals/{person}/forms/availability', [FormController::class, 'saveAvailability']);
    Route::get('/casuals/{person}', [CasualController::class, 'show'])->name('casuals.show');
    Route::put('/casuals/{person}', [CasualController::class, 'update'])->name('casuals.update');

    Route::post('/casuals/{person}/wwcc', [ComplianceController::class, 'wwcc'])->name('casuals.wwcc');
    Route::post('/casuals/{person}/accreditation', [ComplianceController::class, 'accreditation'])->name('casuals.accreditation');
    Route::post('/casuals/{person}/alignment', [ComplianceController::class, 'alignment'])->name('casuals.alignment');
    Route::post('/casuals/{person}/induction/{module}', [InductionController::class, 'acknowledge'])->name('casuals.acknowledge');
    Route::post('/casuals/{person}/contracts', [ContractController::class, 'issue'])->name('casuals.contracts.issue');
    Route::post('/contracts/{contract}/sign', [ContractController::class, 'sign'])->name('contracts.sign');
    Route::post('/casuals/{person}/bookings', [BookingController::class, 'store'])->name('casuals.bookings');
    Route::post('/casuals/{person}/unavailability', [RecordController::class, 'unavailability'])->name('casuals.unavailability');
    Route::post('/casuals/{person}/notes', [RecordController::class, 'dayNote'])->name('casuals.notes');
    Route::post('/casuals/{person}/pd', [RecordController::class, 'pd'])->name('casuals.pd');
    Route::post('/casuals/{person}/invite', [InviteController::class, 'store'])->name('casuals.invite');

    Route::middleware('role:hr,conductor')->group(function () {
        Route::get('/setup', [SetupController::class, 'index'])->name('setup');
        Route::get('/setup/staff', [SetupController::class, 'staff'])->name('setup.staff');
        Route::post('/setup/staff', [SetupController::class, 'storeStaff'])->name('setup.staff.store');
        Route::get('/setup/staff/{member}', [SetupController::class, 'editStaff'])->name('setup.staff.edit');
        Route::put('/setup/staff/{member}', [SetupController::class, 'updateStaff'])->name('setup.staff.update');
        Route::get('/setup/induction', [SetupController::class, 'induction'])->name('setup.induction');
        Route::post('/setup/induction', [SetupController::class, 'storeInduction'])->name('setup.induction.store');
        Route::get('/setup/induction/{module}', [SetupController::class, 'editInduction'])->name('setup.induction.edit');
        Route::put('/setup/induction/{module}', [SetupController::class, 'updateInduction'])->name('setup.induction.update');
        Route::post('/setup/induction/{module}/move', [SetupController::class, 'moveInduction'])->name('setup.induction.move');
        Route::post('/templates', [ContractController::class, 'store'])->name('templates.store');
        Route::post('/templates/{template}/move', [ContractController::class, 'move'])->name('templates.move');
        Route::post('/templates/{template}/archive', [ContractController::class, 'archive'])->name('templates.archive');
        Route::post('/contracts/{contract}/withdraw', [ContractController::class, 'withdraw'])->name('contracts.withdraw');
        Route::post('/contracts/{contract}/move', [ContractController::class, 'moveContract'])->name('contracts.move');
        Route::get('/templates/complete', [ContractController::class, 'complete'])->name('templates.complete');
        Route::post('/templates/complete', [ContractController::class, 'send'])->name('templates.complete.send');
        Route::get('/templates', [ContractController::class, 'index'])->name('templates.index');
        Route::get('/templates/{template}', [ContractController::class, 'edit'])->name('templates.edit');
        Route::put('/templates/{template}', [ContractController::class, 'update'])->name('templates.update');
        Route::post('/templates/{template}/publish', [ContractController::class, 'publish'])->name('templates.publish');
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/export/nesa', [AuditController::class, 'export'])->name('export.nesa');
        Route::get('/roadmap', [AuditController::class, 'roadmap'])->name('roadmap');
    });
});
