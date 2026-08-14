<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FestivalController;
use App\Http\Controllers\ProductionController;
use App\Livewire\FestivalCalendar;
use App\Livewire\SubscriberForm;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/festivals/search', [FestivalController::class, 'search'])->name('festivals.search');
Route::resource('festivals', FestivalController::class)->only(['index', 'show']);
// Lazy redirect: when a user clicks a festival card on the list page, we
// hit this route which triggers the detail enrichment (1 credit, 24h
// cached per apiId) and 302s to the organizer's real URL. Browsing the
// list costs only 1 credit (the list call) regardless of how many cards
// the user scrolls past — we only spend when intent is signaled.
Route::get('/festivals/{apiId}/redirect', [FestivalController::class, 'redirectToFestival'])
    ->whereNumber('apiId')
    ->name('festivals.redirect');
Route::post('/subscribe', [FestivalController::class, 'subscribe'])
    ->middleware('throttle:subscribe')
    ->name('festivals.subscribe');
Route::delete('/unsubscribe/{festivalApiId}', [FestivalController::class, 'unsubscribe'])->name('festivals.unsubscribe');

// Read endpoints — no throttle (the user is just navigating).
Route::get('/productions', [ProductionController::class, 'index'])->name('productions.index');
Route::get('/productions/create', [ProductionController::class, 'create'])->name('productions.create');
Route::get('/productions/{production}', [ProductionController::class, 'show'])->name('productions.show');
Route::get('/productions/{production}/edit', [ProductionController::class, 'edit'])->name('productions.edit');
Route::get('/productions/{production}/matches', [ProductionController::class, 'matches'])->name('productions.matches');

// Mutating endpoints — capped to deter scripted abuse.
Route::middleware('throttle:productions')->group(function () {
    Route::post('/productions', [ProductionController::class, 'store'])->name('productions.store');
    Route::put('/productions/{production}', [ProductionController::class, 'update'])->name('productions.update');
    Route::patch('/productions/{production}', [ProductionController::class, 'update']);
    Route::delete('/productions/{production}', [ProductionController::class, 'destroy'])->name('productions.destroy');
});

// OAuth 2 with Google — bounded so a callback storm can't create thousands
// of Subscriber rows in seconds.
Route::get('/auth/google', [GoogleController::class, 'redirect'])
    ->middleware('throttle:oauth')
    ->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
    ->middleware('throttle:oauth')
    ->name('auth.google.callback');

// Email-based login and registration. Both POSTs share the `register`
// throttle (5/min/IP) — same surface as the old SubscriberForm Livewire.
Route::get('/login', [AuthController::class, 'showLogin'])->name('auth.login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:register')
    ->name('auth.login.process');
Route::get('/register', [AuthController::class, 'showRegister'])->name('auth.register');
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:register')
    ->name('auth.register.process');

// Password reset flow — anti-enumeration + tight cap.
Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('auth.forgot-password');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])
    ->middleware('throttle:password-reset')
    ->name('auth.password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('auth.password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:password-reset')
    ->name('auth.password.store');

// Authenticated dashboard — gated by auth.subscriber middleware.
// remember.subscriber runs first so a returning user with a valid cookie
// doesn't see the "inicia sesión" flash.
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['remember.subscriber', 'auth.subscriber'])
    ->name('dashboard');

// Logout: cap so a script can't churn sessions.
Route::post('/logout', [GoogleController::class, 'logout'])
    ->middleware('throttle:logout')
    ->name('auth.logout');
