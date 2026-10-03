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
use App\Http\Controllers\NotebookController;
use App\Http\Controllers\NoteExportController;
use App\Http\Controllers\NoteLabelController;
use App\Http\Controllers\NoteLinkController;
use App\Http\Controllers\NoteQuickCaptureController;
use App\Http\Controllers\PlannerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimeTrackingController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\WorkoutPlanController;
use App\Http\Controllers\WorkoutSessionController;
use App\Http\Controllers\WorkoutReportController;
use App\Http\Controllers\WorkoutImportController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

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
    Route::post('routines/reorder', [RoutineController::class, 'reorder'])->name('routines.reorder');
    Route::post('routines/steps/reorder', [RoutineController::class, 'reorderSteps'])->name('routines.steps.reorder');
    Route::prefix('workouts')->name('workouts.')->group(function () {
        Route::resource('exercises', ExerciseController::class)->except(['show']);
        Route::resource('plans', WorkoutPlanController::class)->except(['show'])->parameters(['plans' => 'workoutPlan']);
        Route::post('plans/{workoutPlan}/new-cycle', [WorkoutPlanController::class, 'newCycle'])->name('plans.new-cycle');
        Route::post('days/{workoutDay}/session', [WorkoutSessionController::class, 'start'])->name('sessions.start');
        Route::get('sessions/{workoutSession}', [WorkoutSessionController::class, 'show'])->name('sessions.show');
        Route::post('sessions/{workoutSession}/sets', [WorkoutSessionController::class, 'saveSet'])->name('sessions.sets.store');
        Route::patch('sessions/{workoutSession}/finish', [WorkoutSessionController::class, 'finish'])->name('sessions.finish');
        Route::get('reports', [WorkoutReportController::class, 'index'])->name('reports.index');
        Route::get('reports/exercises/{exercise}', [WorkoutReportController::class, 'exercise'])->name('reports.exercise');
        Route::get('imports/create', [WorkoutImportController::class, 'create'])->name('imports.create');
        Route::post('imports', [WorkoutImportController::class, 'store'])->name('imports.store');
        Route::get('imports/{workoutImport}', [WorkoutImportController::class, 'show'])->name('imports.show');
        Route::post('imports/{workoutImport}/confirm', [WorkoutImportController::class, 'confirm'])->name('imports.confirm');
    });
    Route::post('routines/{routine}/new-cycle', [RoutineController::class, 'newCycle'])->name('routines.new-cycle');
    Route::get('routines/{routine}/stats', [RoutineController::class, 'stats'])->name('routines.stats');
    Route::get('routines/showAll', [RoutineController::class, 'showAll'])->name('routines.showAll');
    Route::get('routines/daily', [RoutineController::class, 'showDaily'])->name('routines.showDaily');
    Route::get('routines/weekly', [RoutineController::class, 'showWeekly'])->name('routines.showWeekly');
    Route::get('routines/monthly', [RoutineController::class, 'showMonthly'])->name('routines.showMonthly');
    Route::get('/track', [TrackController::class, 'index'])->name('track.index');
    Route::resource('files', FileController::class);

    // ── Notes ───────────────────────────────────────────────────
    // Static segments MUST be registered before Route::resource('notes'),
    // otherwise notes/{note} swallows them.
    Route::post('notes/quick-capture', [NoteQuickCaptureController::class, 'store'])->name('notes.quick-capture');
    Route::get('notes/mentions', [NoteQuickCaptureController::class, 'mentions'])->name('notes.mentions');
    Route::get('notes/timeline', [NoteController::class, 'index'])->defaults('view', 'timeline')->name('notes.timeline');
    Route::get('notes/export/markdown', [NoteExportController::class, 'markdown'])->name('notes.export.markdown');
    Route::get('notes/export/preview', [NoteExportController::class, 'preview'])->name('notes.export.preview');
    Route::post('notes/collections', [\App\Http\Controllers\NoteCollectionController::class, 'store'])->name('notes.collections.store');
    Route::post('notes/send-to-ai', [\App\Http\Controllers\NoteAiController::class, 'sendCollection'])->name('notes.ai.send');
    Route::get('notes/{note}/extract', [\App\Http\Controllers\NoteAiController::class, 'extractPreview'])->name('notes.ai.extract');
    Route::post('notes/{note}/extract', [\App\Http\Controllers\NoteAiController::class, 'extractApply'])->name('notes.ai.extract.apply');
    Route::post('note-subjects/{subject}/summarize', [\App\Http\Controllers\NoteAiController::class, 'summarizeSubject'])->name('note-subjects.summarize');
    Route::get('notes/collections/{collection}/apply', [\App\Http\Controllers\NoteCollectionController::class, 'apply'])->name('notes.collections.apply');
    Route::delete('notes/collections/{collection}', [\App\Http\Controllers\NoteCollectionController::class, 'destroy'])->name('notes.collections.destroy');
    Route::get('notes/backlinks/{type}', [NoteLinkController::class, 'backlinks'])->name('notes.backlinks.index');
    Route::get('notes/{note}/revisions', [NoteController::class, 'revisions'])->name('notes.revisions.index');
    Route::post('notes/{note}/revisions/{revision}/restore', [NoteController::class, 'restoreRevision'])->name('notes.revisions.restore');
    Route::post('notes/{note}/toggle-pin', [NoteController::class, 'togglePin'])->name('notes.toggle-pin');
    Route::post('notes/{note}/toggle-archive', [NoteController::class, 'toggleArchive'])->name('notes.toggle-archive');
    Route::patch('notes/{note}/toggle-favorite', [NoteController::class, 'toggleFavorite'])->name('notes.toggle-favorite');
    Route::post('notes/{note}/duplicate', [NoteController::class, 'duplicate'])->name('notes.duplicate');
    Route::post('notes/{note}/links', [NoteLinkController::class, 'store'])->name('notes.links.store');
    Route::delete('notes/{note}/links/{link}', [NoteLinkController::class, 'destroy'])->name('notes.links.destroy');
    Route::resource('notes', NoteController::class);

    // ── Notebooks & curated labels ──────────────────────────────
    Route::get('notebooks', [NotebookController::class, 'index'])->name('notebooks.index');
    Route::post('notebooks/reorder', [NotebookController::class, 'reorder'])->name('notebooks.reorder');
    Route::resource('notebooks', NotebookController::class)->only(['store', 'update', 'destroy'])
        ->parameters(['notebooks' => 'notebook']);
    Route::resource('note-labels', NoteLabelController::class)->only(['store', 'update', 'destroy'])
        ->parameters(['note-labels' => 'noteLabel']);
    Route::post('note-labels/{noteLabel}/merge', [NoteLabelController::class, 'merge'])->name('note-labels.merge');
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
    Route::post('/planner/routines/{routine}/skip', [PlannerController::class, 'skipRoutine'])->name('planner.routines.skip');
    Route::post('/planner/routines/{routine}/log', [PlannerController::class, 'logRoutine'])->name('planner.routines.log');
    Route::post('/planner/check-items/{item}/toggle', [PlannerController::class, 'toggleCheckItem'])->name('planner.check-items.toggle');
    Route::post('/planner/check-items/{item}/skip', [PlannerController::class, 'skipCheckItem'])->name('planner.check-items.skip');
    Route::post('/planner/routines/{routine}/slip', [PlannerController::class, 'logViolation'])->name('planner.routines.slip');
    Route::post('/planner/check-items/{item}/slip', [PlannerController::class, 'logStepViolation'])->name('planner.check-items.slip');
    Route::post('/planner/routines/{routine}/note', [PlannerController::class, 'logRoutineNote'])->name('planner.routines.note');
    Route::delete('/planner/violations/{violation}', [PlannerController::class, 'destroyViolation'])->name('planner.violations.destroy');
    Route::patch('/planner/violations/{violation}', [PlannerController::class, 'updateViolation'])->name('planner.violations.update');
    Route::delete('/planner/notes/{note}', [PlannerController::class, 'destroyNote'])->name('planner.notes.destroy');
    Route::patch('/planner/notes/{note}', [PlannerController::class, 'updateNote'])->name('planner.notes.update');
    Route::get('/planner/routines/{routine}/suggestions', [PlannerController::class, 'avoidSuggestions'])->name('planner.routines.suggestions');
    Route::get('/planner/routines/{routine}/history', [PlannerController::class, 'avoidHistory'])->name('planner.routines.history');
    Route::post('/planner/task-items/{item}/toggle', [PlannerController::class, 'toggleTaskCheckItem'])->name('planner.task-items.toggle');
    Route::post('/planner/quick-add/task', [PlannerController::class, 'quickAddTask'])->name('planner.quick-add.task');
    Route::post('/planner/quick-add/routine', [PlannerController::class, 'quickAddRoutine'])->name('planner.quick-add.routine');
    Route::post('/planner/tasks/{task}/postpone', [PlannerController::class, 'postponeTask'])->name('planner.tasks.postpone');
    Route::post('/planner/tasks/{task}/fail', [PlannerController::class, 'failTask'])->name('planner.tasks.fail');
    Route::post('/planner/tasks/{task}/unfail', [PlannerController::class, 'unfailTask'])->name('planner.tasks.unfail');
    Route::post('/planner/tasks/{task}/title', [PlannerController::class, 'renameTask'])->name('planner.tasks.title');
    Route::post('/planner/tasks/{task}/estimate', [PlannerController::class, 'updateEstimate'])->name('planner.tasks.estimate');
    Route::post('/planner/tasks/reorder', [PlannerController::class, 'reorderTasks'])->name('planner.tasks.reorder');
    Route::post('/planner/tasks/postpone-all', [PlannerController::class, 'postponeAllOverdue'])->name('planner.tasks.postpone-all');
    Route::post('/planner/ai-optimize', [PlannerController::class, 'aiOptimize'])->name('planner.ai-optimize');
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
    Route::get('/ai/debug', [AiChatController::class, 'debug'])->name('ai.debug');
    // AI tool actions (confirm/reject with ownership + expiry checks)
    Route::post('/ai/actions/confirm-all', [AiActionController::class, 'confirmAll'])
        ->middleware('throttle:30,1')->name('ai.actions.confirm-all');
    Route::post('/ai/actions/reject-all', [AiActionController::class, 'rejectAll'])
        ->middleware('throttle:30,1')->name('ai.actions.reject-all');
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

    // Unified reports (tasks · routines · avoid habits · workouts · time)
    Route::get('/reports', [\App\Http\Controllers\ReportsController::class, 'overview'])->name('reports.overview');

    // Dashboard routes
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/productivity-data', [DashboardController::class, 'getProductivityData'])->name('dashboard.productivity-data');
    Route::post('/morning-check-in/dismiss', [DashboardController::class, 'dismissMorningCheckin'])->name('morning-checkin.dismiss');
});
