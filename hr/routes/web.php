<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CasualController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\WatchController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\CasualLetterController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\InductionController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\PartnerDeskController;
use App\Http\Controllers\PartnerJoinController;
use App\Http\Controllers\PartnerSetupController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SetupController;
use App\Hr\Roles;
use App\Http\Controllers\StartController;
use App\Http\Controllers\ViewAsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check() && ! Roles::isCasual(auth()->user())) {
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
Route::get('/partners/join', [PartnerJoinController::class, 'create'])->name('partners.join');
Route::post('/partners/join', [PartnerJoinController::class, 'store'])->name('partners.join.store');
Route::get('/partners/go/{partner:token}', [PartnerJoinController::class, 'course'])->name('partners.course');
Route::get('/partners/go/{partner:token}/pages/{slug}', [PartnerJoinController::class, 'page'])->name('partners.page');
Route::post('/partners/go/{partner:token}/pages/{slug}', [PartnerJoinController::class, 'next'])->name('partners.page.next');
Route::get('/partners/go/{partner:token}/check', [PartnerJoinController::class, 'check'])->name('partners.check');
Route::post('/partners/go/{partner:token}/check', [PartnerJoinController::class, 'saveCheck'])->name('partners.check.save');
Route::get('/partners/go/{partner:token}/about', [PartnerJoinController::class, 'about'])->name('partners.about');
Route::post('/partners/go/{partner:token}/about', [PartnerJoinController::class, 'saveAbout'])->name('partners.about.save');
Route::get('/partners/go/{partner:token}/done', [PartnerJoinController::class, 'done'])->name('partners.done');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
    Route::get('/invites/{token}', [InviteController::class, 'accept'])->name('invites.accept');
    Route::post('/invites/{token}', [InviteController::class, 'complete']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::post('/see/stop', [ViewAsController::class, 'stop'])->name('view-as.stop');
    Route::get('/people', [PeopleController::class, 'index'])->name('people');
    Route::get('/today', [DayController::class, 'index'])->name('today');
    Route::post('/today/{person}/on', [DayController::class, 'arrive'])->name('today.on');
    Route::post('/today/{person}/off', [DayController::class, 'leave'])->name('today.off');
    Route::post('/today/{person}/close', [DayController::class, 'close'])->name('today.close');
    Route::get('/watch', [WatchController::class, 'index'])->name('watch');
    Route::get('/access', [AccessController::class, 'show'])->name('access');
    Route::get('/partners', [PartnerDeskController::class, 'index'])->name('partners.index');
    Route::get('/partners/{partner}', [PartnerDeskController::class, 'show'])->name('partners.show');
    Route::post('/partners/{partner}/check', [PartnerDeskController::class, 'volunteer'])->name('partners.volunteer');
    Route::post('/people/order', [PeopleController::class, 'order'])->middleware('manages')->name('people.order');
    Route::get('/people/{member}/photo', [PeopleController::class, 'photo'])->name('people.photo');
    Route::get('/casuals', [CasualController::class, 'index'])->name('casuals.index');
    Route::get('/casuals/new', [CasualController::class, 'create'])->name('casuals.create')->middleware('manages');
    Route::post('/casuals', [CasualController::class, 'store'])->name('casuals.store')->middleware('manages');
    Route::get('/casuals/{person}/induction', [InductionController::class, 'pack'])->name('casuals.pack');
    Route::get('/casuals/{person}/induction/{module}', [InductionController::class, 'show'])->name('casuals.induction');
    Route::get('/casuals/{person}/forms', [FormController::class, 'index'])->name('casuals.forms');
    Route::get('/casuals/{person}/forms/personal', [FormController::class, 'personal'])->name('casuals.forms.personal');
    Route::post('/casuals/{person}/forms/personal', [FormController::class, 'savePersonal']);
    Route::get('/casuals/{person}/forms/accreditation', [FormController::class, 'accreditation'])->name('casuals.forms.accreditation');
    Route::get('/casuals/{person}/forms/wwcc', [FormController::class, 'wwcc'])->name('casuals.forms.wwcc');
    Route::get('/casuals/{person}/forms/availability', [FormController::class, 'availability'])->name('casuals.forms.availability');
    Route::post('/casuals/{person}/forms/availability', [FormController::class, 'saveAvailability']);
    Route::get('/casuals/{person}/casual-status-letter', [CasualLetterController::class, 'show'])->middleware('manages')->name('casuals.letter');
    Route::post('/casuals/{person}/see', [ViewAsController::class, 'start'])->middleware('manages')->name('view-as.start');
    Route::get('/casuals/{person}', [CasualController::class, 'show'])->name('casuals.show');
    Route::put('/casuals/{person}', [CasualController::class, 'update'])->name('casuals.update');

    Route::post('/casuals/{person}/wwcc', [ComplianceController::class, 'wwcc'])->name('casuals.wwcc');
    Route::post('/casuals/{person}/accreditation', [ComplianceController::class, 'accreditation'])->name('casuals.accreditation');
    Route::post('/casuals/{person}/alignment', [ComplianceController::class, 'alignment'])->name('casuals.alignment');
    Route::post('/casuals/{person}/induction/{module}', [InductionController::class, 'acknowledge'])->name('casuals.acknowledge');
    Route::post('/casuals/{person}/contracts', [ContractController::class, 'issue'])->name('casuals.contracts.issue');
    Route::post('/contracts/{contract}/sign', [ContractController::class, 'sign'])->name('contracts.sign');
    Route::post('/casuals/{person}/bookings', [BookingController::class, 'store'])->name('casuals.bookings');
    Route::post('/casuals/{person}/bookings/{booking}/note', [BookingController::class, 'note'])->name('casuals.bookings.note');
    Route::post('/casuals/{person}/conduct', [RecordController::class, 'conduct'])->name('casuals.conduct');
    Route::post('/casuals/{person}/standards', [RecordController::class, 'standards'])->name('casuals.standards');
    Route::post('/casuals/{person}/standards/{notice}/reply', [RecordController::class, 'standardsReply'])->name('casuals.standards.reply');
    Route::post('/casuals/{person}/unavailability', [RecordController::class, 'unavailability'])->name('casuals.unavailability');
    Route::post('/casuals/{person}/notes', [RecordController::class, 'dayNote'])->name('casuals.notes');
    Route::post('/casuals/{person}/goal', [RecordController::class, 'goal'])->name('casuals.goal');
    Route::post('/casuals/{person}/observation', [RecordController::class, 'observation'])->name('casuals.observation');
    Route::get('/casuals/{person}/annual', [RecordController::class, 'annual'])->name('casuals.annual');
    Route::post('/casuals/{person}/annual', [RecordController::class, 'saveAnnual']);
    Route::post('/casuals/{person}/mentor', [CasualController::class, 'nameMentor'])->name('casuals.mentor');
    Route::post('/casuals/{person}/pd', [RecordController::class, 'pd'])->name('casuals.pd');
    Route::post('/casuals/{person}/invite', [InviteController::class, 'store'])->name('casuals.invite');

    Route::middleware('manages')->group(function () {
        Route::get('/setup', [SetupController::class, 'index'])->name('setup');
        Route::get('/setup/roles', [RoleController::class, 'index'])->name('setup.roles');
        Route::post('/setup/roles', [RoleController::class, 'store'])->name('setup.roles.store');
        Route::get('/setup/roles/{role}', [RoleController::class, 'edit'])->name('setup.roles.edit');
        Route::put('/setup/roles/{role}', [RoleController::class, 'update'])->name('setup.roles.update');
        Route::delete('/setup/roles/{role}', [RoleController::class, 'destroy'])->name('setup.roles.destroy');
        Route::get('/setup/staff', [SetupController::class, 'staff'])->name('setup.staff');
        Route::post('/setup/staff', [SetupController::class, 'storeStaff'])->name('setup.staff.store');
        Route::get('/setup/staff/{member}', [SetupController::class, 'editStaff'])->name('setup.staff.edit');
        Route::put('/setup/staff/{member}', [SetupController::class, 'updateStaff'])->name('setup.staff.update');
        Route::get('/setup/induction', [SetupController::class, 'induction'])->name('setup.induction');
        Route::post('/setup/induction', [SetupController::class, 'storeInduction'])->name('setup.induction.store');
        Route::get('/setup/induction/{module}', [SetupController::class, 'editInduction'])->name('setup.induction.edit');
        Route::put('/setup/induction/{module}', [SetupController::class, 'updateInduction'])->name('setup.induction.update');
        Route::put('/setup/induction/{module}/check', [SetupController::class, 'updateCheck'])->name('setup.induction.check');
        Route::put('/setup/desk', [SetupController::class, 'desk'])->name('setup.desk');
        Route::post('/setup/induction/{module}/move', [SetupController::class, 'moveInduction'])->name('setup.induction.move');
        Route::get('/setup/partners', [PartnerSetupController::class, 'index'])->name('setup.partners');
        Route::get('/setup/partners/check', [PartnerSetupController::class, 'check'])->name('setup.partners.check');
        Route::put('/setup/partners/check', [PartnerSetupController::class, 'updateCheck'])->name('setup.partners.check.update');
        Route::get('/setup/partners/{page}', [PartnerSetupController::class, 'edit'])->name('setup.partners.edit');
        Route::put('/setup/partners/{page}', [PartnerSetupController::class, 'update'])->name('setup.partners.update');
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
