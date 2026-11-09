<?php

namespace App\Models;

use App\Http\Controllers\VocabCardController;
use App\Http\Controllers\VocabReviewController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'avatar',
        'bio',
        'phone',
        'location',
        'website',
        'locale',
        'morning_checkin_enabled',
        'morning_window_start',
        'morning_window_end',
        'wake_routine_id',
        'vocab_daily_limit',
        'vocab_session_limit',
        'vocab_remind_enabled',
        'onboarding_state',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'is_admin',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'morning_checkin_enabled' => 'boolean',
        'vocab_remind_enabled' => 'boolean',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
        'onboarding_state' => 'array',
    ];

    public function isAdministrator(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Administrator-side account state: active, suspended (banned) or
     * soft-deleted. Soft-deleted accounts are hidden from every default
     * query and can never authenticate.
     */
    public function status(): string
    {
        if ($this->deleted_at !== null) {
            return 'deleted';
        }

        return $this->is_active === false ? 'banned' : 'active';
    }

    /**
     * The routine used for the morning wake-up check-in.
     */
    public function wakeRoutine()
    {
        return $this->belongsTo(Routine::class, 'wake_routine_id');
    }

    /**
     * Morning check-in window as [start, end] in "H:i" format.
     *
     * @return array{0: string, 1: string}
     */
    public function morningWindow(): array
    {
        return [
            substr((string) ($this->morning_window_start ?? '04:00:00'), 0, 5),
            substr((string) ($this->morning_window_end ?? '12:00:00'), 0, 5),
        ];
    }

    /**
     * Max new vocabulary cards the user may add per day (1–100).
     */
    public function vocabDailyLimit(): int
    {
        return max(1, min(100, (int) ($this->vocab_daily_limit ?? VocabCardController::DAILY_NEW_CARD_LIMIT)));
    }

    /**
     * Max due cards covered by a single vocabulary review session (1–100).
     */
    public function vocabSessionLimit(): int
    {
        return max(1, min(100, (int) ($this->vocab_session_limit ?? VocabReviewController::SESSION_LIMIT)));
    }

    /**
     * Get the projects for the user.
     */
    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the tasks for the user.
     */
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the routines for the user.
     */
    public function routines()
    {
        return $this->hasMany(Routine::class);
    }

    public function exercises()
    {
        return $this->hasMany(Exercise::class);
    }

    public function workoutPlans()
    {
        return $this->hasMany(WorkoutPlan::class);
    }

    public function workoutSessions()
    {
        return $this->hasMany(WorkoutSession::class);
    }

    public function workoutImports()
    {
        return $this->hasMany(WorkoutImport::class);
    }

    /**
     * Get the notes for the user.
     */
    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function notebooks()
    {
        return $this->hasMany(Notebook::class);
    }

    public function noteLabels()
    {
        return $this->hasMany(NoteLabel::class);
    }

    public function noteShares()
    {
        return $this->hasMany(NoteShare::class);
    }

    public function noteSubjects()
    {
        return $this->hasMany(NoteSubject::class);
    }

    /**
     * Get the calendar events for the user.
     */
    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function vocabDecks()
    {
        return $this->hasMany(VocabDeck::class);
    }

    public function vocabCards()
    {
        return $this->hasMany(VocabCard::class);
    }

    public function vocabReviews()
    {
        return $this->hasMany(VocabReview::class);
    }

    public function projectMembers()
    {
        return $this->belongsToMany(Project::class, 'project_teams', 'user_id', 'project_id');
    }

    public function aiProviders()
    {
        return $this->hasMany(AiProvider::class);
    }

    public function routineCompletions()
    {
        return $this->hasMany(RoutineCompletion::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function dayLogs()
    {
        return $this->hasMany(DayLog::class);
    }

    public function journalBooks()
    {
        return $this->hasMany(JournalBook::class);
    }

    public function journalMoods()
    {
        return $this->hasMany(JournalMood::class);
    }

    public function journalTags()
    {
        return $this->hasMany(JournalTag::class);
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function laws()
    {
        return $this->hasMany(Law::class);
    }

    public function lawEvents()
    {
        return $this->hasMany(LawEvent::class);
    }
}
