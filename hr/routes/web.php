<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CasualController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\InductionController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('casuals.index')
        : view('auth.login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);
    Route::get('/invites/{token}', [InviteController::class, 'accept'])->name('invites.accept');
    Route::post('/invites/{token}', [InviteController::class, 'complete']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/casuals', [CasualController::class, 'index'])->name('casuals.index');
    Route::get('/casuals/new', [CasualController::class, 'create'])->name('casuals.create')->middleware('role:hr,conductor');
    Route::post('/casuals', [CasualController::class, 'store'])->name('casuals.store')->middleware('role:hr,conductor');
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
        Route::get('/templates', [ContractController::class, 'index'])->name('templates.index');
        Route::get('/templates/{template}', [ContractController::class, 'edit'])->name('templates.edit');
        Route::put('/templates/{template}', [ContractController::class, 'update'])->name('templates.update');
        Route::post('/templates/{template}/publish', [ContractController::class, 'publish'])->name('templates.publish');
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/export/nesa', [AuditController::class, 'export'])->name('export.nesa');
        Route::get('/roadmap', [AuditController::class, 'roadmap'])->name('roadmap');
    });
});
