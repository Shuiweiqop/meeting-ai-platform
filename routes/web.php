<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChunkUploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SharedMeetingController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TodoItemController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/share/{token}', [SharedMeetingController::class, 'show'])->name('share.show');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/calendar', [CalendarController::class, '__invoke'])->name('calendar');
    Route::get('/calendar/export', [CalendarController::class, 'export'])->name('calendar.export');

    Route::resource('meetings', MeetingController::class);
    Route::get('/meetings/{meeting}/audio', [MeetingController::class, 'audio'])->name('meetings.audio');
    Route::get('/meetings/{meeting}/export', [MeetingController::class, 'exportPdf'])->name('meetings.export');
    Route::post('/meetings/{meeting}/retry', [MeetingController::class, 'retry'])->name('meetings.retry');
    Route::post('/meetings/{meeting}/share', [SharedMeetingController::class, 'generate'])->name('meetings.share.generate');
    Route::delete('/meetings/{meeting}/share', [SharedMeetingController::class, 'revoke'])->name('meetings.share.revoke');
    Route::post('/upload/chunk', [ChunkUploadController::class, 'chunk'])->name('upload.chunk');
    Route::post('/upload/merge', [ChunkUploadController::class, 'merge'])->name('upload.merge');

    Route::get('/todos', [TodoItemController::class, 'index'])->name('todos.index');
    Route::patch('/todo-items/{todoItem}', [TodoItemController::class, 'update'])->name('todo-items.update');

    Route::resource('teams', TeamController::class)->except(['edit', 'update']);
    Route::post('/teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.add');
    Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.members.remove');
});

require __DIR__.'/auth.php';
