<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarImportController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\IcsCalendarController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SessionSwapController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('calendar.index');
});

// Public ICS feed endpoint
Route::get('/calendar/ics/{token}', IcsCalendarController::class)->name('calendar.ics');

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit')->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit')->middleware('throttle:6,1');
});

// Quick login: registrato SOLO in ambiente locale (account demo con password nota).
if (app()->environment('local')) {
    Route::middleware('guest')->get('/quick-login/{role}', [AuthController::class, 'quickLogin'])->name('quick.login');
}

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Main Calendar Dashboard (pubblico, sola lettura)
Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
Route::get('/calendar/events', [CalendarController::class, 'events'])->name('calendar.events');
Route::get('/calendar/availability', [CalendarController::class, 'availability'])->name('calendar.availability');

Route::middleware('auth')->group(function () {
    // Verifica email (senza middleware `verified`: l'utente non verificato deve poter navigare)
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')->name('verification.send');

    // Events Management
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    // Richieste di scambio sessioni
    Route::get('/swap-requests', [SessionSwapController::class, 'index'])->name('swap-requests.index');
    Route::post('/events/{event}/swap-requests', [SessionSwapController::class, 'store'])->name('swap-requests.store');
    Route::delete('/swap-requests/{swapRequest}', [SessionSwapController::class, 'destroy'])->name('swap-requests.destroy');

    // Admin Group Management (CD & ADMIN)
    Route::prefix('admin')->name('admin.')->middleware('can:manage-groups')->group(function () {
        Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
        Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
        Route::put('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
        Route::post('/groups/{group}/users', [GroupController::class, 'assignUser'])->name('groups.users.assign');
        Route::delete('/groups/{group}/users/{user}', [GroupController::class, 'removeUser'])->name('groups.users.remove');
        Route::put('/groups/{group}/users/{user}/manager', [GroupController::class, 'setManager'])->name('groups.users.manager');
    });

    // Manage users (Admin & CD)
    Route::prefix('admin')->name('admin.')->middleware('can:manage-users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
    });

    // Manage rooms (Admin & CD)
    Route::prefix('admin')->name('admin.')->middleware('can:manage-rooms')->group(function () {
        Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    });

    // Importazione calendario da Excel/CSV (Admin & CD)
    Route::prefix('admin/import/calendar')->name('admin.import.')->middleware('can:import-calendar')->group(function () {
        Route::get('/', [CalendarImportController::class, 'create'])->name('create');
        Route::post('/', [CalendarImportController::class, 'store'])->name('store');
        Route::post('/confirm', [CalendarImportController::class, 'confirm'])->name('confirm');
        Route::delete('/', [CalendarImportController::class, 'cancel'])->name('cancel');
    });
});
