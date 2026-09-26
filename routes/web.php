<?php

use App\Http\Controllers\AiActionController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AiPlanController;
use App\Http\Controllers\AiProviderController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ChecklistItemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PlannerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimeTrackingController;
use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    // Profile routes
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('profile/password', [ProfileController::class, 'showPasswordForm'])->name('profile.password');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');

    Route::controller(MailController::class)->prefix('mail')->name('mail.')->group(function () {
        Route::get('/', 'index')->name('inbox');
    });
    Route::post('projects/reorder', [ProjectController::class, 'reorder'])->name('projects.reorder');
    Route::resource('projects', ProjectController::class);
    Route::post('project/team', [ProjectController::class, 'addMember'])->name('projects.addMember');
    Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('projects.tasks.index');
    Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('projects.tasks.store');

    Route::post('tasks/reorder', [TaskController::class, 'reorder'])->name('tasks.reorder');
    Route::post('tasks/bulk-update', [TaskController::class, 'bulkUpdate'])->name('tasks.bulk-update');
    Route::delete('tasks/bulk-destroy', [TaskController::class, 'bulkDestroy'])->name('tasks.bulk-destroy');
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('tasks/{task}/update-status', [TaskController::class, 'updateStatus']);
    Route::post('tasks/{task}/add-to-day', [TaskController::class, 'addToDay'])->name('tasks.add-to-day');

    Route::resource('routines', RoutineController::class)->except(['show']);
    Route::post('routines/{routine}/new-cycle', [RoutineController::class, 'newCycle'])->name('routines.new-cycle');
    Route::get('routines/{routine}/stats', [RoutineController::class, 'stats'])->name('routines.stats');
    Route::get('routines/showAll', [RoutineController::class, 'showAll'])->name('routines.showAll');
    Route::get('routines/daily', [RoutineController::class, 'showDaily'])->name('routines.showDaily');
    Route::get('routines/weekly', [RoutineController::class, 'showWeekly'])->name('routines.showWeekly');
    Route::get('routines/monthly', [RoutineController::class, 'showMonthly'])->name('routines.showMonthly');
    Route::get('/track', [TrackController::class, 'index'])->name('track.index');
    Route::resource('files', FileController::class);
    Route::resource('notes', NoteController::class);
    Route::patch('notes/{note}/toggle-favorite', [NoteController::class, 'toggleFavorite'])->name('notes.toggle-favorite');
    Route::post('notes/{note}/duplicate', [NoteController::class, 'duplicate'])->name('notes.duplicate');
    Route::resource('reminders', ReminderController::class);
    Route::post('reminders/{reminder}/toggle-complete', [ReminderController::class, 'toggleComplete'])->name('reminders.toggle-complete');
    Route::post('reminders/{reminder}/snooze', [ReminderController::class, 'snooze'])->name('reminders.snooze');
    Route::post('reminders/{reminder}/duplicate', [ReminderController::class, 'duplicate'])->name('reminders.duplicate');
    Route::get('reminders-calendar', [ReminderController::class, 'calendar'])->name('reminders.calendar');
    Route::resource('checklist-items', ChecklistItemController::class);
    Route::get('checklist-items/{checklistItem}/update-status', [ChecklistItemController::class, 'updateStatus'])->name('checklist-items.update-status');

    // My Day / Planner
    Route::get('/planner', [PlannerController::class, 'index'])->name('planner.index');
    Route::post('/planner/tasks/{task}/toggle', [PlannerController::class, 'toggleTask'])->name('planner.tasks.toggle');
    Route::post('/planner/routines/{routine}/toggle', [PlannerController::class, 'toggleRoutine'])->name('planner.routines.toggle');
    Route::post('/planner/routines/{routine}/log', [PlannerController::class, 'logRoutine'])->name('planner.routines.log');
    Route::post('/planner/check-items/{item}/toggle', [PlannerController::class, 'toggleCheckItem'])->name('planner.check-items.toggle');
    Route::post('/planner/task-items/{item}/toggle', [PlannerController::class, 'toggleTaskCheckItem'])->name('planner.task-items.toggle');
    Route::post('/planner/quick-add/task', [PlannerController::class, 'quickAddTask'])->name('planner.quick-add.task');
    Route::post('/planner/quick-add/routine', [PlannerController::class, 'quickAddRoutine'])->name('planner.quick-add.routine');
    Route::get('/planner/next-up', [PlannerController::class, 'nextUp'])->name('planner.next-up');

    // Time tracking
    Route::get('/time/active', [TimeTrackingController::class, 'active'])->name('time.active');
    Route::post('/time/start', [TimeTrackingController::class, 'start'])->name('time.start');
    Route::get('/time/tasks', [TimeTrackingController::class, 'tasksForProject'])->name('time.tasks');
    Route::post('/time/entries/{entry}/pause', [TimeTrackingController::class, 'pause'])->name('time.pause');
    Route::post('/time/entries/{entry}/resume', [TimeTrackingController::class, 'resume'])->name('time.resume');
    Route::post('/time/entries/{entry}/stop', [TimeTrackingController::class, 'stop'])->name('time.stop');
    Route::get('/time/reports', [TimeTrackingController::class, 'reports'])->name('time.reports');
    Route::post('/time/entries', [TimeTrackingController::class, 'store'])->name('time.entries.store');
    Route::delete('/time/entries/{entry}', [TimeTrackingController::class, 'destroy'])->name('time.entries.destroy');

    // AI Chat
    Route::get('/ai', [AiChatController::class, 'index'])->name('ai.index');
    Route::get('/ai/status', [AiChatController::class, 'status'])->name('ai.status');
    Route::get('/ai/settings', [AiSettingsController::class, 'index'])->name('ai.settings');
    Route::put('/ai/settings', [AiSettingsController::class, 'update'])->name('ai.settings.update');
    Route::post('/ai/settings/switch', [AiSettingsController::class, 'quickSwitch'])->name('ai.settings.switch');
    // Custom (user-defined) AI providers
    Route::post('/ai/providers', [AiProviderController::class, 'store'])->name('ai.providers.store');
    Route::put('/ai/providers/{provider}', [AiProviderController::class, 'update'])->name('ai.providers.update');
    Route::delete('/ai/providers/{provider}', [AiProviderController::class, 'destroy'])->name('ai.providers.destroy');
    Route::post('/ai/providers/{provider}/test', [AiProviderController::class, 'test'])->name('ai.providers.test');
    Route::post('/ai/chat', [AiChatController::class, 'chat'])->name('ai.chat');
    Route::post('/ai/stream', [AiChatController::class, 'stream'])->name('ai.stream');
    // AI tool actions (confirm/reject with ownership + expiry checks)
    Route::post('/ai/actions/{action}/confirm', [AiActionController::class, 'confirm'])
        ->middleware('throttle:30,1')->name('ai.actions.confirm');
    Route::post('/ai/actions/{action}/reject', [AiActionController::class, 'reject'])
        ->middleware('throttle:30,1')->name('ai.actions.reject');
    // AI structured plans (structure approval + phased execution)
    Route::post('/ai/plans/{plan}/confirm-structure', [AiPlanController::class, 'confirmStructure'])
        ->middleware('throttle:30,1')->name('ai.plans.confirm-structure');
    Route::post('/ai/plans/{plan}/confirm-phase', [AiPlanController::class, 'confirmPhase'])
        ->middleware('throttle:30,1')->name('ai.plans.confirm-phase');
    Route::post('/ai/plans/{plan}/cancel', [AiPlanController::class, 'cancel'])
        ->middleware('throttle:30,1')->name('ai.plans.cancel');
    // AI Conversations (DB-backed)
    Route::get('/ai/conversations', [AiChatController::class, 'conversations'])->name('ai.conversations.index');
    Route::post('/ai/conversations', [AiChatController::class, 'createConversation'])->name('ai.conversations.create');
    Route::get('/ai/conversations/{conversation}', [AiChatController::class, 'getConversation'])->name('ai.conversations.show');
    Route::patch('/ai/conversations/{conversation}/rename', [AiChatController::class, 'renameConversation'])->name('ai.conversations.rename');
    Route::delete('/ai/conversations/{conversation}', [AiChatController::class, 'deleteConversation'])->name('ai.conversations.delete');
    Route::post('/ai/conversations/{conversation}/clear', [AiChatController::class, 'clearConversation'])->name('ai.conversations.clear');

    // Dashboard routes
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/productivity-data', [DashboardController::class, 'getProductivityData'])->name('dashboard.productivity-data');
});
