<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\IcsCalendarController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return redirect()->route('calendar.index');
});

// Public ICS feed endpoint
Route::get('/calendar/ics/{token}', IcsCalendarController::class)->name('calendar.ics');

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
    Route::get('/quick-login/{role}', [AuthController::class, 'quickLogin'])->name('quick.login');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Main Calendar Dashboard
Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

// Events Management
Route::middleware('auth')->group(function () {
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    // Admin Group Management (CD & ADMIN)
    Route::prefix('admin')->name('admin.')->middleware('can:manage-groups')->group(function () {
        Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
        Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
        Route::put('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
        Route::post('/groups/{group}/users', [GroupController::class, 'assignUser'])->name('groups.users.assign');
        Route::delete('/groups/{group}/users/{user}', [GroupController::class, 'removeUser'])->name('groups.users.remove');
    });
        // Manage users (Admin & CD)
        Route::prefix('admin')->name('admin.')->middleware('can:manage-users')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        });

    }); // end auth group
