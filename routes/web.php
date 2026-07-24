<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FestivalController;
use App\Livewire\FestivalCalendar;
use App\Livewire\SubscriberForm;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/festivals/search', [FestivalController::class, 'search'])->name('festivals.search');
Route::resource('festivals', FestivalController::class)->only(['index', 'show']);
Route::post('/subscribe', [FestivalController::class, 'subscribe'])->name('festivals.subscribe');
Route::delete('/unsubscribe/{festivalApiId}', [FestivalController::class, 'unsubscribe'])->name('festivals.unsubscribe');
Route::get('/subscriber/logout', [FestivalController::class, 'logout'])->name('subscriber.logout');
