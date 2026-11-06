<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CasualListController;
use App\Http\Controllers\InductionController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\SessionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/login', [SessionController::class, 'create'])->name('login');
Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:10,1');

Route::get('/casual-induction/{token}', [InductionController::class, 'open'])
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->name('induction.open');

Route::middleware('invite.shell')->group(function () {
    Route::get('/induction', [InductionController::class, 'shell'])->name('induction.shell');
});

Route::post('/induction/leave', [InductionController::class, 'leave'])->name('induction.leave');

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        $role = request()->user()->role;

        return redirect(match ($role) {
            User::DIRECTOR => route('director.index'),
            User::OFFICE => route('office.index'),
            default => route('hr.index'),
        });
    })->name('home');

    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::middleware('role:'.User::HR.','.User::CONDUCTOR)->group(function () {
        Route::get('/hr', [PeopleController::class, 'index'])->name('hr.index');
        Route::get('/hr/people/create', [PeopleController::class, 'create'])->name('hr.people.create');
        Route::post('/hr/people', [PeopleController::class, 'store'])->name('hr.people.store');
        Route::get('/hr/people/{person}', [PeopleController::class, 'show'])->name('hr.people.show');
        Route::put('/hr/people/{person}', [PeopleController::class, 'update'])->name('hr.people.update');
        Route::put('/hr/people/{person}/accreditation', [PeopleController::class, 'updateAccreditation'])->name('hr.people.accreditation');
        Route::put('/hr/people/{person}/alignment', [PeopleController::class, 'updateAlignment'])->name('hr.people.alignment');
        Route::post('/hr/people/{person}/invites', [PeopleController::class, 'invite'])->name('hr.people.invites');
        Route::get('/hr/audit', [AuditController::class, 'index'])->name('hr.audit');
    });

    Route::get('/hr/people/{person}/wwcc', [PeopleController::class, 'showWwcc'])
        ->middleware('wwcc.access')
        ->name('hr.people.wwcc');
    Route::put('/hr/people/{person}/wwcc', [PeopleController::class, 'updateWwcc'])
        ->middleware('wwcc.access')
        ->name('hr.people.wwcc.update');

    Route::middleware('role:'.User::DIRECTOR)->group(function () {
        Route::get('/director', [CasualListController::class, 'director'])->name('director.index');
    });

    Route::middleware('role:'.User::OFFICE)->group(function () {
        Route::get('/office', [CasualListController::class, 'office'])->name('office.index');
    });

    Route::post('/people/{person}/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::post('/bookings/{booking}/conductor-note', [BookingController::class, 'note'])->name('bookings.note');
    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
});
