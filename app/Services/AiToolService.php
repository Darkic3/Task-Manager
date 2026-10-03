<?php

namespace App\Services;

use App\Models\ChecklistItem;
use App\Models\Note;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Routine;
use App\Models\RoutineCompletion;
use App\Models\Task;
use App\Models\User;
use App\Services\Notes\NoteLinkService;
use App\Services\Notes\NoteMentionService;
use App\Services\Reports\ReportRange;
use App\Services\Reports\UnifiedReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AiToolService
{
    public const TOOLS = [
        'task_create', 'task_update', 'task_complete', 'task_delete',
        'reminder_create', 'reminder_complete', 'reminder_delete',
        'note_create', 'note_update', 'note_delete',
        'project_create', 'project_add_member',
        'note_link',
        'report_generate',
        'checklist_add', 'checklist_toggle',
        'routine_create', 'routine_complete', 'routine_delete', 'routine_log',
        'plan_propose', 'workout_plan_propose',
    ];

    public const ROUTINE_VALUE_KINDS = ['number', 'weight', 'time', 'reps', 'percent'];

    public const MAX_PLAN_ROUTINES = 7;
    public const MAX_PLAN_ROUTINE_STEPS = 20;

    public const WEEK_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * OpenAI-compatible function definitions for OpenRouter/custom providers.
     */
    public function definitions(): array
    {
        $date = fn () => ['type' => 'string', 'description' => 'Date as YYYY-MM-DD'];

        return [
            $this->fn('task_create', 'Create a task for the user. Pass parent_id to create a subtask (project is inherited from the parent)', [
                'title' => ['type' => 'string', 'description' => 'Task title'],
                'project' => ['type' => 'string', 'description' => 'Project name or ID (optional)'],
                'project_id' => ['type' => 'integer', 'description' => 'Project ID (optional, preferred over name)'],
                'parent_id' => ['type' => 'integer', 'description' => 'Parent task ID for a subtask (project is inherited)'],
                'due_date' => $date(),
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'status' => ['type' => 'string', 'enum' => ['to_do', 'in_progress', 'on_hold', 'in_review', 'completed']],
                'description' => ['type' => 'string'],
            ], ['title']),
            $this->fn('task_update', 'Edit a task. Pass id, or task (title) with optional project scope — the title is resolved automatically, never ask the user for IDs', [
                'id' => ['type' => 'integer', 'description' => 'Task ID (preferred when known)'],
                'task' => ['type' => 'string', 'description' => 'Task title or ID (used when id is omitted)'],
                'project' => ['type' => 'string', 'description' => 'Project name or ID to scope the title lookup (when titles repeat)'],
                'project_id' => ['type' => 'integer', 'description' => 'Project ID to scope the title lookup'],
                'title' => ['type' => 'string', 'description' => 'New title'],
                'due_date' => $date(),
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'status' => ['type' => 'string', 'enum' => ['to_do', 'in_progress', 'on_hold', 'in_review', 'completed']],
                'description' => ['type' => 'string'],
            ], []),
            $this->fn('task_complete', 'Mark a task completed. Pass id, or task (title) with optional project scope', [
                'id' => ['type' => 'integer', 'description' => 'Task ID (preferred when known)'],
                'task' => ['type' => 'string', 'description' => 'Task title or ID (used when id is omitted)'],
                'project' => ['type' => 'string', 'description' => 'Project name or ID to scope the title lookup'],
                'project_id' => ['type' => 'integer', 'description' => 'Project ID to scope the title lookup'],
            ], []),
            $this->fn('task_delete', 'Delete a task (shows subtask impact before confirm). Pass id, or task (title) with optional project scope', [
                'id' => ['type' => 'integer', 'description' => 'Task ID (preferred when known)'],
                'task' => ['type' => 'string', 'description' => 'Task title or ID (used when id is omitted)'],
                'project' => ['type' => 'string', 'description' => 'Project name or ID to scope the title lookup'],
                'project_id' => ['type' => 'integer', 'description' => 'Project ID to scope the title lookup'],
            ], []),
            $this->fn('reminder_create', 'Create a reminder. Compute date/time yourself from today\'s date (e.g. "امروز ساعت 6 بعد از ظهر" → today 18:00).', [
                'title' => ['type' => 'string'],
                'date' => $date(),
                'time' => ['type' => 'string', 'description' => 'HH:MM 24h'],
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                'description' => ['type' => 'string'],
                'location' => ['type' => 'string', 'description' => 'Place, e.g. a bazaar or person to meet'],
            ], ['title']),
            $this->fn('reminder_complete', 'Mark a reminder completed', [
                'id' => ['type' => 'integer'],
            ], ['id']),
            $this->fn('reminder_delete', 'Delete a reminder', [
                'id' => ['type' => 'integer'],
            ], ['id']),
            $this->fn('note_create', 'Create a note', [
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'category' => ['type' => 'string'],
            ], ['title', 'content']),
            $this->fn('note_update', 'Edit a note by ID', [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'category' => ['type' => 'string'],
            ], ['id']),
            $this->fn('note_delete', 'Delete a note by ID', [
                'id' => ['type' => 'integer'],
            ], ['id']),
            $this->fn('project_create', 'Create a project. Pass parent (name or ID) to create a sub-project', [
                'name' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => ['not_started', 'in_progress', 'completed', 'closed']],
                'parent' => ['type' => 'string', 'description' => 'Parent project name or ID (optional)'],
            ], ['name']),
            $this->fn('project_add_member', 'Add a collaborator to one of the user\'s projects. The member is found by email, name, or user ID.', [
                'project' => ['type' => 'string', 'description' => 'Project name or ID (must be yours)'],
                'project_id' => ['type' => 'integer', 'description' => 'Project ID (preferred over name)'],
                'user' => ['type' => 'string', 'description' => 'Member email, name, or user ID'],
                'role' => ['type' => 'string', 'enum' => ['member', 'viewer', 'editor'], 'description' => 'Team role (default member)'],
            ], ['user']),
            $this->fn('note_link', 'Link a note to a project, task, or another note (shows in backlinks). Both sides must belong to the user.', [
                'note' => ['type' => 'string', 'description' => 'Note title or ID'],
                'note_id' => ['type' => 'integer', 'description' => 'Note ID (preferred over title)'],
                'target' => ['type' => 'string', 'description' => 'Target project/task/note name or ID'],
                'target_type' => ['type' => 'string', 'enum' => ['project', 'task', 'note'], 'description' => 'What kind of target'],
                'target_id' => ['type' => 'integer', 'description' => 'Target ID (preferred over name)'],
            ], ['target_type']),
            $this->fn('report_generate', 'Build a read-only workspace summary (tasks, routines, time, workouts + insights) for today/week/month. Nothing is created or changed.', [
                'range' => ['type' => 'string', 'enum' => ['today', 'week', 'month'], 'description' => 'Report window (default week)'],
            ], []),
            $this->fn('checklist_add', 'Add a checklist item to a task. Pass task_id, or task (title) with optional project scope', [
                'task_id' => ['type' => 'integer', 'description' => 'Task ID (preferred when known)'],
                'task' => ['type' => 'string', 'description' => 'Task title or ID (used when task_id is omitted)'],
                'project' => ['type' => 'string', 'description' => 'Project name or ID to scope the title lookup'],
                'project_id' => ['type' => 'integer', 'description' => 'Project ID to scope the title lookup'],
                'name' => ['type' => 'string'],
            ], ['name']),
            $this->fn('checklist_toggle', 'Toggle a checklist item completed state', [
                'id' => ['type' => 'integer'],
            ], ['id']),
            $this->fn('routine_create', 'Create a recurring routine. Ask the user first when tracking is wanted but the unit kind is unknown', [
                'title' => ['type' => 'string', 'description' => 'Routine title'],
                'frequency' => ['type' => 'string', 'enum' => ['daily', 'weekly', 'monthly', 'every_n_days']],
                'days' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::WEEK_DAYS], 'description' => 'REQUIRED when frequency is weekly. Lowercase weekday names, e.g. ["thursday"] or ["friday","saturday"]. One routine per weekday when workouts differ.'],
                'month_days' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Days of month (1-31) for monthly frequency'],
                'every_n_days' => ['type' => 'integer', 'description' => 'Interval 2-60 for every_n_days frequency'],
                'time_period' => ['type' => 'string', 'description' => 'Time-of-day period key (morning, afternoon, evening, night) if known'],
                'description' => ['type' => 'string', 'description' => 'Short plan summary, under 500 chars'],
                'tracking_mode' => ['type' => 'string', 'enum' => ['none', 'value', 'sets'], 'description' => 'none=checkbox only, value=one number per day, sets=per-set numbers'],
                'value_kind' => ['type' => 'string', 'enum' => self::ROUTINE_VALUE_KINDS, 'description' => 'Required when tracking_mode is value'],
                'value_unit' => ['type' => 'string', 'description' => 'Display unit, e.g. kg'],
                'value_label' => ['type' => 'string', 'description' => 'Label, e.g. Weight'],
                'steps' => [
                    'type' => 'array', 'description' => 'Max 20 steps; each may define target_sets for sets mode',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Max 120 chars'],
                            'target_sets' => ['type' => 'integer', 'description' => 'Sets per day, 1-20'],
                            'unit' => ['type' => 'string'],
                        ],
                        'required' => ['name'],
                        'additionalProperties' => false,
                    ],
                ],
            ], ['title', 'frequency']),
            $this->fn('routine_log', 'Log a tracked number for a routine day (value mode or one set of a step)', [
                'routine' => ['type' => 'string', 'description' => 'Routine name or ID'],
                'routine_id' => ['type' => 'integer', 'description' => 'Routine ID (preferred over name)'],
                'date' => $date(),
                'value' => ['type' => 'number', 'description' => 'Logged number. For time-kind routines pass minutes since midnight (e.g. "07:30" → 450); an "HH:MM" string is also accepted'],
                'item' => ['type' => 'string', 'description' => 'Step name or ID (sets mode only)'],
                'item_id' => ['type' => 'integer', 'description' => 'Step ID (sets mode only, preferred)'],
                'set_no' => ['type' => 'integer', 'description' => 'Set number 1-20 (sets mode, default 1)'],
            ], ['value']),
            $this->fn('routine_complete', 'Mark a routine done for a date (defaults to today). Never un-completes', [
                'id' => ['type' => 'integer'],
                'date' => $date(),
            ], ['id']),
            $this->fn('routine_delete', 'Delete a routine by ID (shows recorded-history impact before confirm)', [
                'id' => ['type' => 'integer'],
            ], ['id']),
            $this->fn('workout_plan_propose', 'Build a structured multi-day WORKOUT plan (7 days with exercises, sets, reps, RIR, rest, tempo, circuits). Use this for ANY pasted training plan — never plan_propose, never routines, never tasks.', [
                'title' => ['type' => 'string', 'description' => 'Plan title, e.g. Week 14 Training Plan'],
                'week_number' => ['type' => 'integer', 'description' => 'Week number if the user stated one, else null'],
                'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD of DAY 1. When the user says starting today, use today\'s date from the system prompt.'],
                'goal' => ['type' => 'string', 'description' => 'Training goal if stated'],
                'description' => ['type' => 'string', 'description' => 'Short plan summary, max 2000 chars'],
                'rules' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Plan rules, safety warnings, excluded movements'],
                'days' => [
                    'type' => 'array',
                    'description' => 'Exactly 7 days in execution order: index 0 is DAY 1 (mapped to start_date), index 6 is DAY 7. Keep the user\'s day order.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => 'Day title, e.g. PULL A'],
                            'type' => ['type' => 'string', 'enum' => ['training', 'rest', 'recovery']],
                            'notes' => ['type' => 'string'],
                            'exercises' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'section' => ['type' => 'string', 'enum' => ['warmup', 'main', 'accessory', 'cooldown']],
                                        'target_sets' => ['type' => 'integer'],
                                        'rep_min' => ['type' => 'integer'],
                                        'rep_max' => ['type' => 'integer'],
                                        'duration_seconds' => ['type' => 'integer', 'description' => 'For timed moves like Plank 40-60s use 40-60 range midpoint or max; prefer duration_seconds over reps'],
                                        'target_weight' => ['type' => 'number'],
                                        'target_rir' => ['type' => 'number'],
                                        'rest_seconds' => ['type' => 'integer'],
                                        'tempo' => ['type' => 'string'],
                                        'side_mode' => ['type' => 'string', 'enum' => ['bilateral', 'per_side', 'alternating']],
                                        'is_amrap' => ['type' => 'boolean'],
                                        'is_circuit' => ['type' => 'boolean'],
                                        'circuit_rounds' => ['type' => 'integer'],
                                        'circuit_rest_seconds' => ['type' => 'integer'],
                                        'notes' => ['type' => 'string', 'description' => 'Tempo cues, safety warnings, alternatives, per-side notes'],
                                    ],
                                    'required' => ['name'],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['title', 'type'],
                        'additionalProperties' => false,
                    ],
                ],
            ], ['title', 'days']),
            [
                'type' => 'function',
                'function' => [
                    'name' => 'plan_propose',
                    'description' => 'Propose a whole multi-level build as ONE plan: up to 5 independent projects (projects[]) each with tasks, OR one project with sub-projects (project+subprojects), plus optional reminders[] and notes[]. NEVER use for workout/training plans — those must use workout_plan_propose.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => 'Short plan title'],
                            'project' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                    'tasks' => [
                                        'type' => 'array',
                                        'description' => 'Tasks directly under the project (no sub-project)',
                                        'items' => $this->planTaskSchema(),
                                    ],
                                ],
                                'required' => ['name'],
                                'additionalProperties' => false,
                            ],
                            'subprojects' => [
                                'type' => 'array',
                                'description' => 'Max 3 sub-projects of the single project (project-tree plans only; do not combine with projects[])',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'description' => ['type' => 'string'],
                                        'tasks' => ['type' => 'array', 'items' => $this->planTaskSchema()],
                                    ],
                                    'required' => ['name'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'projects' => [
                                'type' => 'array',
                                'description' => 'Max 5 INDEPENDENT root projects (multi-project plans). Use this when the user asks for several projects at once; do not combine with project/subprojects.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'description' => ['type' => 'string'],
                                        'tasks' => ['type' => 'array', 'items' => $this->planTaskSchema()],
                                        'members' => ['type' => 'array', 'description' => 'Max 5 collaborator emails or names to add to this project', 'items' => ['type' => 'string']],
                                    ],
                                    'required' => ['name'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'reminders' => [
                                'type' => 'array',
                                'description' => 'Max 10 reminders created with the plan (e.g. an event today at 18:00 with a location). Compute date as YYYY-MM-DD and time as HH:MM from today\'s date.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                        'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                                        'time' => ['type' => 'string', 'description' => 'HH:MM 24h'],
                                        'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                                        'description' => ['type' => 'string'],
                                        'location' => ['type' => 'string'],
                                    ],
                                    'required' => ['title'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'notes' => [
                                'type' => 'array',
                                'description' => 'Max 10 notes created with the plan.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                        'content' => ['type' => 'string'],
                                        'category' => ['type' => 'string'],
                                    ],
                                    'required' => ['title', 'content'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'routines' => [
                                'type' => 'array',
                                'description' => 'Max 7 routines (routine plans only — cannot be combined with a project tree)',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                        'frequency' => ['type' => 'string', 'enum' => ['daily', 'weekly', 'monthly', 'every_n_days']],
                                        'days' => ['type' => 'array', 'items' => ['type' => 'string']],
                                        'tracking_mode' => ['type' => 'string', 'enum' => ['none', 'value', 'sets']],
                                        'value_kind' => ['type' => 'string'],
                                        'value_unit' => ['type' => 'string'],
                                        'value_label' => ['type' => 'string'],
                                        'description' => ['type' => 'string'],
                                        'steps' => [
                                            'type' => 'array',
                                            'description' => 'Max 20 steps with optional target_sets',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'name' => ['type' => 'string'],
                                                    'target_sets' => ['type' => 'integer'],
                                                    'unit' => ['type' => 'string'],
                                                ],
                                                'required' => ['name'],
                                                'additionalProperties' => false,
                                            ],
                                        ],
                                    ],
                                    'required' => ['title', 'frequency'],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['title'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Shared task schema for plan_propose (inlined twice: $ref is not
     * reliably resolved by smaller OpenRouter models).
     */
    private function planTaskSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Max 120 chars'],
                'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'description' => ['type' => 'string', 'description' => 'Max 500 chars'],
                'subtasks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => ['title' => ['type' => 'string', 'description' => 'Max 120 chars']],
                        'required' => ['title'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['title'],
            'additionalProperties' => false,
        ];
    }

    private function fn(string $name, string $desc, array $props, array $required): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $desc,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $props,
                    'required' => $required,
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Validate a proposed call. Returns ['ok'=>bool,'error'=>?string,'resolved'=>array].
     * Never writes to DB.
     */
    public function validateCall(string $tool, array $args, $user): array
    {
        $tool = self::normalizeToolName($tool);
        if (! in_array($tool, self::TOOLS, true)) {
            return $this->fail("Unknown tool: {$tool}");
        }

        $args = $this->normalize($args);

        return match ($tool) {
            'task_create' => $this->validateTaskCreate($args, $user),
            'task_update' => $this->validateTaskUpdate($args, $user),
            'task_complete' => $this->validateTaskComplete($args, $user),
            'task_delete' => $this->validateTaskDelete($args, $user),
            'reminder_create' => $this->validateReminderCreate($args),
            'reminder_complete' => $this->validateOwned($args, $user, Reminder::class, 'id'),
            'reminder_delete' => $this->validateOwned($args, $user, Reminder::class, 'id'),
            'note_create' => $this->validateNoteCreate($args),
            'note_update' => $this->validateNoteUpdate($args, $user),
            'note_delete' => $this->validateOwned($args, $user, Note::class, 'id'),
            'project_create' => $this->validateProjectCreate($args, $user),
            'project_add_member' => $this->validateProjectAddMember($args, $user),
            'note_link' => $this->validateNoteLink($args, $user),
            'report_generate' => $this->validateReportGenerate($args),
            'checklist_add' => $this->validateChecklistAdd($args, $user),
            'checklist_toggle' => $this->validateChecklistToggle($args, $user),
            'routine_create' => $this->validateRoutineCreate($args),
            'routine_complete' => $this->validateRoutineComplete($args, $user),
            'routine_delete' => $this->validateOwned($args, $user, Routine::class, 'id'),
            'routine_log' => $this->validateRoutineLog($args, $user),
            'plan_propose' => $this->validatePlanPropose($args),
            'workout_plan_propose' => $this->validateWorkoutPlanPropose($args),
            default => $this->fail('Unsupported tool'),
        };
    }

    /**
     * Build a human-readable preview for the confirmation card (no DB writes).
     */
    public function preview(string $tool, array $resolved, $user): array
    {
        $tool = self::normalizeToolName($tool);

        return match ($tool) {
            'task_delete' => $this->previewTaskDelete($resolved),
            'task_update' => ['title' => 'Update task', 'rows' => $this->rows($resolved, ['task_title', 'title', 'due_date', 'priority', 'status', 'description'])],
            'task_complete' => ['title' => 'Complete task', 'rows' => $this->rows($resolved, ['task_title'])],
            'project_create' => ['title' => isset($resolved['parent_id']) ? 'Create sub-project' : 'Create project', 'rows' => $this->rows($resolved, ['name', 'parent_name', 'status', 'description'])],
            'task_create' => ['title' => isset($resolved['parent_id']) ? 'Create subtask' : 'Create task', 'rows' => $this->rows($resolved, ['title', 'project_name', 'parent_title', 'due_date', 'priority', 'status'])],
            'reminder_create' => ['title' => 'Create reminder', 'rows' => $this->rows($resolved, ['title', 'date', 'time', 'priority'])],
            'note_create' => ['title' => 'Create note', 'rows' => $this->rows($resolved, ['title', 'category'])],
            'routine_create' => ['title' => 'Create routine', 'rows' => $this->rows($resolved, ['title', 'frequency', 'days_label', 'tracking_mode', 'value_label', 'time_period', 'description'])],
            'routine_delete' => $this->previewRoutineDelete($resolved),
            'routine_log' => $this->previewRoutineLog($resolved),
            'project_add_member' => ['title' => 'Add project member', 'rows' => $this->rows($resolved, ['project_name', 'member_name', 'member_email', 'role'])],
            'note_link' => ['title' => 'Link note', 'rows' => $this->rows($resolved, ['note_title', 'target_kind', 'target_title'])],
            'report_generate' => ['title' => 'Generate report', 'rows' => $this->rows($resolved, ['range', 'range_label'])],
            'plan_propose' => $this->previewPlan($resolved),
            'workout_plan_propose' => $this->previewWorkoutPlan($resolved),
            default => ['title' => ucfirst(str_replace('_', ' ', $tool)), 'rows' => $this->rows($resolved, array_keys($resolved))],
        };
    }

    /**
     * Execute a validated pending action inside a transaction.
     * Returns ['ok'=>bool,'message'=>string,'id'=>?int].
     */
    public function execute(string $tool, array $resolved, $user): array
    {
        $tool = self::normalizeToolName($tool);

        return DB::transaction(function () use ($tool, $resolved, $user) {
            return match ($tool) {
                'task_create' => $this->execTaskCreate($resolved, $user),
                'task_update' => $this->execTaskUpdate($resolved, $user),
                'task_complete' => $this->execTaskComplete($resolved, $user),
                'task_delete' => $this->execTaskDelete($resolved, $user),
                'reminder_create' => $this->execReminderCreate($resolved, $user),
                'reminder_complete' => $this->execReminderComplete($resolved, $user),
                'reminder_delete' => $this->execDelete($resolved, $user, Reminder::class, 'Reminder'),
                'note_create' => $this->execNoteCreate($resolved, $user),
                'note_update' => $this->execNoteUpdate($resolved, $user),
                'note_delete' => $this->execDelete($resolved, $user, Note::class, 'Note'),
                'project_create' => $this->execProjectCreate($resolved, $user),
                'project_add_member' => $this->execProjectAddMember($resolved, $user),
                'note_link' => $this->execNoteLink($resolved, $user),
                'report_generate' => $this->execReportGenerate($resolved, $user),
                'checklist_add' => $this->execChecklistAdd($resolved, $user),
                'checklist_toggle' => $this->execChecklistToggle($resolved, $user),
                'routine_create' => $this->execRoutineCreate($resolved, $user),
                'routine_complete' => $this->execRoutineComplete($resolved, $user),
                'routine_delete' => $this->execRoutineDelete($resolved, $user),
                'routine_log' => $this->execRoutineLog($resolved, $user),
                // Plans never execute as a single action; they run phase by phase.
                'plan_propose' => ['ok' => false, 'message' => 'Plans run phase by phase after structure approval.', 'id' => null],
                // Workout plans are created via the WorkoutImport preview/confirm flow, not as a single action.
                'workout_plan_propose' => ['ok' => false, 'message' => 'Workout plans are created via the import preview after confirmation.', 'id' => null],
                default => ['ok' => false, 'message' => 'Unsupported tool', 'id' => null],
            };
        });
    }

    // ── validators ──

    private function validateTaskCreate(array $args, $user): array
    {
        $v = Validator::make($args, [
            'title' => 'required|string|max:255',
            'project_id' => 'nullable|integer',
            'project' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:to_do,in_progress,on_hold,in_review,completed',
            'description' => 'nullable|string',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }

        // Subtask: parent must belong to the user; project is inherited.
        $parentId = $args['parent_id'] ?? null;
        $parentTitle = null;
        if ($parentId) {
            $parent = Task::where('id', $parentId)->where('user_id', $user->id)->first();
            if (! $parent) {
                return $this->fail('Parent task not found or not yours.');
            }
            $parentTitle = $parent->title;

            return ['ok' => true, 'error' => null, 'resolved' => [
                'title' => $args['title'],
                'project_id' => $parent->project_id,
                'project_name' => $parent->project?->name,
                'parent_id' => $parent->id,
                'parent_title' => $parentTitle,
                'due_date' => $args['due_date'] ?? null,
                'priority' => $args['priority'] ?? 'medium',
                'status' => $args['status'] ?? 'to_do',
                'description' => $args['description'] ?? null,
            ]];
        }

        $projectId = $args['project_id'] ?? null;
        $projectName = null;
        if ($projectId) {
            $project = Project::where('id', $projectId)->where('user_id', $user->id)->first();
            if (! $project) {
                return $this->fail('Project not found or not yours.');
            }
            $projectName = $project->name;
        } elseif (! empty($args['project'])) {
            $needle = $args['project'];
            $project = is_numeric($needle)
                ? Project::where('id', (int) $needle)->where('user_id', $user->id)->first()
                : Project::where('user_id', $user->id)->where('name', 'like', "%{$needle}%")->first();
            if (! $project) {
                return $this->fail("Project '{$needle}' not found.");
            }
            $projectId = $project->id;
            $projectName = $project->name;
        } else {
            // No project given — the task simply has no project (allowed).
            $projectId = null;
            $projectName = null;
        }

        return ['ok' => true, 'error' => null, 'resolved' => [
            'title' => $args['title'],
            'project_id' => $projectId,
            'project_name' => $projectName,
            'due_date' => $args['due_date'] ?? null,
            'priority' => $args['priority'] ?? 'medium',
            'status' => $args['status'] ?? 'to_do',
            'description' => $args['description'] ?? null,
        ]];
    }

    private function validateTaskUpdate(array $args, $user): array
    {
        $v = Validator::make($args, [
            'id' => 'nullable|integer',
            'task' => 'nullable|string|max:255',
            'project' => 'nullable|string|max:255',
            'project_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:to_do,in_progress,on_hold,in_review,completed',
            'description' => 'nullable|string',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $r = $this->resolveTask($args, $user);
        if (isset($r['error'])) {
            return $this->fail($r['error']);
        }
        $task = $r['task'];

        return ['ok' => true, 'error' => null, 'resolved' => array_merge(['id' => $task->id, 'task_title' => $task->title], array_filter([
            'title' => $args['title'] ?? null,
            'due_date' => $args['due_date'] ?? null,
            'priority' => $args['priority'] ?? null,
            'status' => $args['status'] ?? null,
            'description' => $args['description'] ?? null,
        ], fn ($x) => $x !== null))];
    }

    private function validateTaskComplete(array $args, $user): array
    {
        $v = Validator::make($args, [
            'id' => 'nullable|integer',
            'task' => 'nullable|string|max:255',
            'project' => 'nullable|string|max:255',
            'project_id' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $r = $this->resolveTask($args, $user);
        if (isset($r['error'])) {
            return $this->fail($r['error']);
        }

        return ['ok' => true, 'error' => null, 'resolved' => ['id' => $r['task']->id, 'task_title' => $r['task']->title]];
    }

    private function validateTaskDelete(array $args, $user): array
    {
        $v = Validator::make($args, [
            'id' => 'nullable|integer',
            'task' => 'nullable|string|max:255',
            'project' => 'nullable|string|max:255',
            'project_id' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $r = $this->resolveTask($args, $user);
        if (isset($r['error'])) {
            return $this->fail($r['error']);
        }

        return ['ok' => true, 'error' => null, 'resolved' => ['id' => $r['task']->id, 'title' => $r['task']->title]];
    }

    private function validateOwned(array $args, $user, string $model, string $key = 'id'): array
    {
        $v = Validator::make($args, [$key => 'required|integer']);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $row = $model::where('id', $args[$key])->where('user_id', $user->id)->first();
        if (! $row) {
            return $this->fail('Item not found or not yours.');
        }

        return ['ok' => true, 'error' => null, 'resolved' => ['id' => $row->id, 'title' => $row->title ?? $row->name ?? "#{$row->id}"]];
    }

    private function validateReminderCreate(array $args): array
    {
        $v = Validator::make($args, [
            'title' => 'required|string|max:255',
            'date' => 'nullable|date',
            'time' => 'nullable|date_format:H:i',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }

        // Persian fallback: "امروز ساعت 6 بعد از ظهر" inside title/description
        // fills missing date/time so the model doesn't have to compute them.
        $date = $args['date'] ?? null;
        $time = $args['time'] ?? null;
        if (! $date || ! $time) {
            $parsed = FaDateParser::parse(trim(($args['title'] ?? '') . ' ' . ($args['description'] ?? '')));
            $date = $date ?: ($parsed['date'] ?? null);
            $time = $time ?: ($parsed['time'] ?? null);
        }

        return ['ok' => true, 'error' => null, 'resolved' => [
            'title' => $args['title'],
            'date' => $date,
            'time' => $time,
            'priority' => $args['priority'] ?? 'medium',
            'description' => $args['description'] ?? null,
            'location' => isset($args['location']) ? trim((string) $args['location']) : null,
        ]];
    }

    private function validateNoteCreate(array $args): array
    {
        $v = Validator::make($args, [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'nullable|string|max:100',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }

        return ['ok' => true, 'error' => null, 'resolved' => [
            'title' => $args['title'], 'content' => $args['content'], 'category' => $args['category'] ?? null,
        ]];
    }

    private function validateNoteUpdate(array $args, $user): array
    {
        $v = Validator::make($args, [
            'id' => 'required|integer',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:100',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $note = Note::where('id', $args['id'])->where('user_id', $user->id)->first();
        if (! $note) {
            return $this->fail('Note not found or not yours.');
        }

        return ['ok' => true, 'error' => null, 'resolved' => array_merge(['id' => $note->id], array_filter([
            'title' => $args['title'] ?? null,
            'content' => $args['content'] ?? null,
            'category' => $args['category'] ?? null,
        ], fn ($x) => $x !== null))];
    }

    private function validateNoteLink(array $args, $user): array
    {
        $v = Validator::make($args, [
            'note_id' => 'nullable|integer',
            'note' => 'nullable|string|max:255',
            'target_type' => 'required|in:project,task,note',
            'target_id' => 'nullable|integer',
            'target' => 'nullable|string|max:255',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }

        $note = null;
        if (! empty($args['note_id'])) {
            $note = Note::where('id', $args['note_id'])->where('user_id', $user->id)->first();
        } elseif (! empty($args['note'])) {
            $needle = $args['note'];
            $note = is_numeric($needle)
                ? Note::where('id', (int) $needle)->where('user_id', $user->id)->first()
                : Note::where('user_id', $user->id)->where('title', 'like', "%{$needle}%")->first();
        }
        if (! $note) {
            return $this->fail('Note not found or not yours.');
        }

        $typeMap = [
            'project' => Project::class,
            'task' => Task::class,
            'note' => Note::class,
        ];
        $type = $typeMap[$args['target_type']];
        $nameColumn = NoteMentionService::LINKABLE[$type];

        $target = null;
        if (! empty($args['target_id'])) {
            $target = $type::where('id', $args['target_id'])->where('user_id', $user->id)->first();
        } elseif (! empty($args['target'])) {
            $needle = $args['target'];
            $target = is_numeric($needle)
                ? $type::where('id', (int) $needle)->where('user_id', $user->id)->first()
                : $type::where('user_id', $user->id)->where($nameColumn, 'like', "%{$needle}%")->first();
        }
        if (! $target) {
            return $this->fail("Target {$args['target_type']} not found or not yours.");
        }
        if ($type === Note::class && (int) $target->id === (int) $note->id) {
            return $this->fail('A note cannot link to itself.');
        }

        return ['ok' => true, 'error' => null, 'resolved' => [
            'note_id' => $note->id,
            'note_title' => $note->title,
            'target_kind' => $args['target_type'],
            'target_type' => $type,
            'target_id' => $target->id,
            'target_title' => (string) $target->{$nameColumn},
        ]];
    }

    private function validateReportGenerate(array $args): array
    {
        $v = Validator::make($args, [
            'range' => 'nullable|in:today,week,month',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $range = $args['range'] ?? 'week';

        return ['ok' => true, 'error' => null, 'resolved' => [
            'range' => $range,
            'range_label' => ['today' => 'Today', 'week' => 'This week', 'month' => 'This month'][$range],
        ]];
    }

    private function validateProjectCreate(array $args, $user = null): array
    {
        $v = Validator::make($args, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:not_started,in_progress,completed,closed',
            'parent' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }

        $parentId = $args['parent_id'] ?? null;
        $parentName = null;
        $needle = $parentId ?? ($args['parent'] ?? null);
        if ($needle !== null && $needle !== '') {
            $parent = is_numeric($needle)
                ? Project::where('id', (int) $needle)->where('user_id', $user->id)->first()
                : Project::where('user_id', $user->id)->where('name', 'like', "%{$needle}%")->first();
            if (! $parent) {
                return $this->fail("Parent project '{$needle}' not found or not yours.");
            }
            if ($this->projectDepth($parent) >= 5) {
                return $this->fail('Parent project is nested too deep (max 5 levels).');
            }
            $parentId = $parent->id;
            $parentName = $parent->name;
        }

        return ['ok' => true, 'error' => null, 'resolved' => [
            'name' => $args['name'],
            'description' => $args['description'] ?? null,
            'status' => $args['status'] ?? 'not_started',
            'parent_id' => $parentId,
            'parent_name' => $parentName,
        ]];
    }

    private function validateProjectAddMember(array $args, $user): array
    {
        $v = Validator::make($args, [
            'project_id' => 'nullable|integer',
            'project' => 'nullable|string|max:255',
            'user' => 'required|string|max:255',
            'role' => 'nullable|in:member,viewer,editor',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }

        $projectId = $args['project_id'] ?? null;
        $project = null;
        if ($projectId) {
            $project = Project::where('id', $projectId)->where('user_id', $user->id)->first();
            if (! $project) {
                return $this->fail('Project not found or not yours.');
            }
        } elseif (! empty($args['project'])) {
            $needle = $args['project'];
            $project = is_numeric($needle)
                ? Project::where('id', (int) $needle)->where('user_id', $user->id)->first()
                : Project::where('user_id', $user->id)->where('name', 'like', "%{$needle}%")->first();
            if (! $project) {
                return $this->fail("Project '{$needle}' not found.");
            }
        } else {
            return $this->fail('Tell me which project to add the member to.');
        }

        $member = $this->resolveMemberUser($args['user']);
        if (! $member) {
            return $this->fail("User '{$args['user']}' not found. Use their email, name, or user ID.");
        }
        if ((int) $member->id === (int) $user->id) {
            return $this->fail("That's you — you already own '{$project->name}'.");
        }

        return ['ok' => true, 'error' => null, 'resolved' => [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'member_id' => $member->id,
            'member_name' => $member->name,
            'member_email' => $member->email,
            'role' => $args['role'] ?? 'member',
        ]];
    }

    /**
     * Find any user by ID, exact email, or name fragment.
     */
    private function resolveMemberUser(string $ref): ?User
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        if (is_numeric($ref)) {
            return User::find((int) $ref);
        }
        if (str_contains($ref, '@')) {
            return User::where('email', $ref)->first();
        }

        return User::where('name', 'like', "%{$ref}%")->orderBy('id')->first();
    }

    /**
     * Depth of a project (root = 0) following loaded-or-queried parents.
     */
    private function projectDepth(Project $project): int
    {
        $depth = 0;
        $current = $project;
        $guard = 0;
        while ($current->parent_id && $guard++ < 10) {
            $depth++;
            $current = $current->relationLoaded('parent') && $current->parent
                ? $current->parent
                : Project::find($current->parent_id);
            if (! $current) {
                break;
            }
        }

        return $depth;
    }

    private function validateChecklistAdd(array $args, $user): array
    {
        $v = Validator::make($args, [
            'task_id' => 'nullable|integer',
            'task' => 'nullable|string|max:255',
            'project' => 'nullable|string|max:255',
            'project_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $r = $this->resolveTask($args, $user, 'task_id', 'task');
        if (isset($r['error'])) {
            return $this->fail($r['error']);
        }
        $task = $r['task'];

        return ['ok' => true, 'error' => null, 'resolved' => ['task_id' => $task->id, 'task_title' => $task->title, 'name' => $args['name']]];
    }

    private function validateChecklistToggle(array $args, $user): array
    {
        $v = Validator::make($args, ['id' => 'required|integer']);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $item = ChecklistItem::where('id', $args['id'])->whereHas('task', fn ($q) => $q->where('user_id', $user->id))->first();
        if (! $item) {
            return $this->fail('Checklist item not found or not yours.');
        }

        return ['ok' => true, 'error' => null, 'resolved' => ['id' => $item->id, 'name' => $item->name]];
    }

    private function validateRoutineCreate(array $args): array
    {
        // Accept a single weekday string as well as an array.
        if (isset($args['days']) && is_string($args['days'])) {
            $args['days'] = [$args['days']];
        }
        if (isset($args['days']) && is_array($args['days'])) {
            $args['days'] = array_values(array_unique(array_map(fn ($d) => strtolower(trim((string) $d)), $args['days'])));
        }
        if (isset($args['month_days']) && is_array($args['month_days'])) {
            $args['month_days'] = array_values(array_unique(array_map('intval', $args['month_days'])));
            sort($args['month_days']);
        }

        // Canonicalize / infer frequency. Smaller models often send a synonym
        // ("everyday", "every_other_day") or send the schedule fields
        // (days / month_days / every_n_days) but forget `frequency` entirely,
        // which used to reject the whole routine instead of creating it.
        if (isset($args['frequency'])) {
            $f = strtolower(trim((string) $args['frequency']));
            $args['frequency'] = [
                'everyday' => 'daily', 'every_day' => 'daily', 'each_day' => 'daily',
                'every_week' => 'weekly',
                'every_month' => 'monthly',
                'every_other_day' => 'every_n_days', 'everyotherday' => 'every_n_days',
                'every_2_days' => 'every_n_days', 'everyndays' => 'every_n_days',
                'every_n_day' => 'every_n_days',
            ][$f] ?? $f;
        }
        if (empty($args['frequency'])) {
            if (! empty($args['every_n_days'])) {
                $args['frequency'] = 'every_n_days';
            } elseif (! empty($args['days'])) {
                $args['frequency'] = 'weekly';
            } elseif (! empty($args['month_days'])) {
                $args['frequency'] = 'monthly';
            } else {
                $args['frequency'] = 'daily';
            }
        }

        $v = Validator::make($args, [
            'title' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,monthly,every_n_days',
            'days' => 'nullable|array|min:1',
            'days.*' => 'string|in:' . implode(',', self::WEEK_DAYS),
            'month_days' => 'nullable|array|min:1',
            'month_days.*' => 'integer|between:1,31',
            'every_n_days' => 'nullable|integer|min:2|max:60',
            'time_period' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:2000',
            'behavior_type' => 'nullable|in:build,avoid',
            'count_violations' => 'nullable|boolean',
            'tracking_mode' => 'nullable|in:none,value,sets',
            'value_kind' => 'nullable|in:' . implode(',', self::ROUTINE_VALUE_KINDS),
            'value_unit' => 'nullable|string|max:20',
            'value_label' => 'nullable|string|max:100',
            'steps' => 'nullable|array|max:20',
            'steps.*.name' => 'required|string|max:120',
            'steps.*.target_sets' => 'nullable|integer|min:1|max:20',
            'steps.*.unit' => 'nullable|string|max:20',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }

        $tracking = $args['tracking_mode'] ?? 'none';
        if ($tracking === 'value' && empty($args['value_kind'])) {
            return $this->fail('Value-tracked routines need a value kind (ask the user which unit).');
        }

        $steps = [];
        foreach (array_values((array) ($args['steps'] ?? [])) as $i => $s) {
            $steps[] = [
                'name' => trim((string) $s['name']),
                'target_sets' => max(1, min(20, (int) ($s['target_sets'] ?? 1))),
                'unit' => isset($s['unit']) && trim((string) $s['unit']) !== '' ? mb_substr(trim((string) $s['unit']), 0, 20) : null,
                'sort_order' => $i,
            ];
        }

        $frequency = $args['frequency'];
        if ($frequency === 'weekly' && empty($args['days'])) {
            return $this->fail('Weekly routines need days, e.g. {"frequency":"weekly","days":["thursday"]}. Send one weekday per routine when workouts differ.');
        }
        if ($frequency === 'monthly' && empty($args['month_days'])) {
            return $this->fail('Monthly routines need at least one day of month.');
        }
        if ($frequency === 'every_n_days' && empty($args['every_n_days'])) {
            return $this->fail('Every-N-days routines need the interval (2-60).');
        }

        $daysLabel = null;
        if ($frequency === 'weekly') {
            $daysLabel = collect($args['days'])->map(fn ($d) => ucfirst(substr($d, 0, 3)))->implode(', ');
        } elseif ($frequency === 'monthly') {
            $daysLabel = 'Day ' . implode(', ', $args['month_days']);
        } elseif ($frequency === 'every_n_days') {
            $n = (int) $args['every_n_days'];
            $daysLabel = $n === 2 ? 'Every other day' : "Every {$n} days";
        } else {
            $daysLabel = 'Every day';
        }

        return ['ok' => true, 'error' => null, 'resolved' => [
            'title' => $args['title'],
            'frequency' => $frequency,
            'days' => $frequency === 'weekly' ? $args['days'] : null,
            'month_days' => $frequency === 'monthly' ? $args['month_days'] : null,
            'every_n_days' => $frequency === 'every_n_days' ? max(2, (int) $args['every_n_days']) : null,
            'time_period' => $args['time_period'] ?? null,
            'description' => $args['description'] ?? null,
            'behavior_type' => $args['behavior_type'] ?? 'build',
            'count_violations' => ! empty($args['count_violations']),
            'days_label' => $daysLabel,
            'tracking_mode' => $tracking,
            'value_kind' => $tracking === 'value' ? $args['value_kind'] : null,
            'value_unit' => $tracking === 'value' ? ($args['value_unit'] ?? null) : null,
            'value_label' => $tracking === 'value' ? ($args['value_label'] ?? null) : null,
            'steps' => $steps,
        ]];
    }

    /**
     * Resolve a task by ID or title (owned by the user), optionally scoped
     * to a project. Exact title match wins, otherwise a LIKE search.
     * Returns ['task' => Task] or ['error' => message the model can act on].
     */
    private function resolveTask(array $args, $user, string $idKey = 'id', string $titleKey = 'task'): array
    {
        if (! empty($args[$idKey])) {
            $task = Task::where('id', (int) $args[$idKey])->where('user_id', $user->id)->first();
            if (! $task) {
                return ['error' => 'Task not found or not yours.'];
            }

            return ['task' => $task];
        }

        $needle = trim((string) ($args[$titleKey] ?? ''));
        if ($needle === '') {
            return ['error' => 'Pass the task id or its title (task).'];
        }
        if (is_numeric($needle)) {
            $task = Task::where('id', (int) $needle)->where('user_id', $user->id)->first();
            if (! $task) {
                return ['error' => 'Task not found or not yours.'];
            }

            return ['task' => $task];
        }

        $query = Task::where('user_id', $user->id);
        $projectName = null;
        if (! empty($args['project_id'])) {
            $query->where('project_id', (int) $args['project_id']);
        } elseif (! empty($args['project'])) {
            $pneedle = $args['project'];
            $project = is_numeric($pneedle)
                ? Project::where('id', (int) $pneedle)->where('user_id', $user->id)->first()
                : Project::where('user_id', $user->id)->where('name', 'like', "%{$pneedle}%")->first();
            if (! $project) {
                return ['error' => "Project '{$pneedle}' not found."];
            }
            $query->where('project_id', $project->id);
            $projectName = $project->name;
        }

        $exact = (clone $query)->where('title', $needle)->get();
        $matches = $exact->isNotEmpty()
            ? $exact
            : (clone $query)->where('title', 'like', "%{$needle}%")->limit(6)->get();

        if ($matches->isEmpty()) {
            $scope = $projectName ? " in project '{$projectName}'" : '';

            return ['error' => "Task '{$needle}' not found{$scope}."];
        }
        if ($matches->count() > 1) {
            $list = $matches->take(5)->map(fn ($t) => "'{$t->title}'")->join(', ');
            $hint = $projectName ? ' Pass the task id to pick one.' : ' Pass project to disambiguate, or the task id.';

            return ['error' => "Multiple tasks match '{$needle}': {$list}.{$hint}"];
        }

        return ['task' => $matches->first()];
    }

    /**
      * Resolve a tracked routine by ID or name (owned by the user).
      */
    private function resolveRoutine(array $args, $user): ?Routine
    {
        if (! empty($args['routine_id'])) {
            return Routine::where('id', (int) $args['routine_id'])->where('user_id', $user->id)->first();
        }
        if (! empty($args['routine'])) {
            $needle = $args['routine'];

            return is_numeric($needle)
                ? Routine::where('id', (int) $needle)->where('user_id', $user->id)->first()
                : Routine::where('user_id', $user->id)->where('title', 'like', "%{$needle}%")->first();
        }

        return null;
    }

    private function validateRoutineLog(array $args, $user): array
    {
        $v = Validator::make($args, [
            'routine' => 'nullable|string|max:255',
            'routine_id' => 'nullable|integer',
            'date' => 'nullable|date',
            'value' => 'required',
            'item' => 'nullable|string|max:255',
            'item_id' => 'nullable|integer',
            'set_no' => 'nullable|integer|min:1|max:20',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }

        $routine = $this->resolveRoutine($args, $user);
        if (! $routine) {
            return $this->fail('Routine not found or not yours.');
        }
        if (! $routine->isTracked()) {
            return $this->fail("Routine '{$routine->title}' has tracking disabled.");
        }

        $isTime = $routine->isTimeValue();
        $value = $args['value'];
        if ($isTime && is_string($value) && preg_match('/^\s*(\d{1,2})[:.](\d{2})\s*$/', $value, $m)) {
            // "07:30" / "7.30" → minutes from midnight.
            $value = (int) $m[1] * 60 + (int) $m[2];
        }
        if (! is_numeric($value)) {
            return $this->fail($isTime
                ? 'Value must be an int (minutes since midnight) or an "HH:MM" string like "07:30".'
                : 'Value must be a number.');
        }
        $value = (float) $value;
        if ($isTime && ($value < 0 || $value > 1439)) {
            return $this->fail('Time values must be within 0-1439 minutes (midnight to 23:59).');
        }
        if ($value < 0 || $value > 1000000) {
            return $this->fail('Value out of range.');
        }

        $date = isset($args['date']) ? Carbon::parse($args['date'])->toDateString() : now()->toDateString();
        $itemId = null;
        $itemName = null;
        if ($routine->tracking_mode === Routine::TRACKING_SETS) {
            $needle = $args['item_id'] ?? ($args['item'] ?? null);
            if ($needle === null || $needle === '') {
                return $this->fail('Sets mode needs the step (item or item_id).');
            }
            $item = is_numeric($needle)
                ? $routine->checklistItems()->whereKey((int) $needle)->first()
                : $routine->checklistItems()->where('name', 'like', "%{$needle}%")->first();
            if (! $item) {
                return $this->fail('Step not found in this routine.');
            }
            $itemId = $item->id;
            $itemName = $item->name;
        }

        return ['ok' => true, 'error' => null, 'resolved' => [
            'routine_id' => $routine->id,
            'routine_title' => $routine->title,
            'tracking_mode' => $routine->tracking_mode,
            'unit' => $routine->tracking_mode === Routine::TRACKING_VALUE ? $routine->value_unit : null,
            'is_time' => $isTime,
            'date' => $date,
            'value' => $value,
            'value_display' => $isTime ? Routine::minutesToTimeValue($value) : (string) $value,
            'item_id' => $itemId,
            'item_name' => $itemName,
            'set_no' => max(1, min(20, (int) ($args['set_no'] ?? 1))),
        ]];
    }

    private function validateRoutineComplete(array $args, $user): array
    {
        $v = Validator::make($args, [
            'id' => 'required|integer',
            'date' => 'nullable|date',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $routine = Routine::where('id', $args['id'])->where('user_id', $user->id)->first();
        if (! $routine) {
            return $this->fail('Routine not found or not yours.');
        }
        $date = isset($args['date']) ? Carbon::parse($args['date'])->toDateString() : now()->toDateString();

        return ['ok' => true, 'error' => null, 'resolved' => [
            'id' => $routine->id, 'title' => $routine->title, 'date' => $date,
        ]];
    }

    /**
     * Validate a whole build plan WITHOUT writing anything.
     * Caps: 5 root projects, 3 sub-projects each, 30 tasks, 100 subtasks,
     * 10 reminders, 10 notes. Routines stay exclusive (no mixing).
     */
    private function validatePlanPropose(array $args): array
    {
        $args['subprojects'] = $args['subProjects'] ?? $args['subprojects'] ?? [];
        unset($args['subProjects']);
        if (! is_array($args['subprojects'])) {
            return $this->fail('subprojects must be a list.');
        }
        $args['projects'] = $args['Projects'] ?? $args['projects'] ?? [];
        unset($args['Projects']);
        if (! is_array($args['projects'])) {
            return $this->fail('projects must be a list.');
        }
        $args['projects'] = array_values($args['projects']);
        $args['reminders'] = array_values((array) ($args['reminders'] ?? []));
        $args['notes'] = array_values((array) ($args['notes'] ?? []));
        $args['routines'] = array_values((array) ($args['routines'] ?? []));

        $v = Validator::make($args, [
            'title' => 'required|string|max:120',
            'project' => 'nullable|array',
            'project.name' => 'nullable|string|max:255',
            'project.description' => 'nullable|string|max:2000',
            'project.tasks' => 'nullable|array',
            'subprojects' => 'nullable|array|max:' . \App\Models\AiPlan::MAX_SUBPROJECTS,
            'subprojects.*.name' => 'required|string|max:255',
            'subprojects.*.description' => 'nullable|string|max:2000',
            'subprojects.*.tasks' => 'nullable|array',
            'projects' => 'nullable|array|max:' . \App\Models\AiPlan::MAX_PROJECTS,
            'projects.*.name' => 'required|string|max:255',
            'projects.*.description' => 'nullable|string|max:2000',
            'projects.*.tasks' => 'nullable|array',
            'projects.*.members' => 'nullable|array|max:5',
            'projects.*.members.*' => 'string|max:255',
            'project.members' => 'nullable|array|max:5',
            'project.members.*' => 'string|max:255',
            'reminders' => 'nullable|array|max:' . \App\Models\AiPlan::MAX_REMINDERS,
            'notes' => 'nullable|array|max:' . \App\Models\AiPlan::MAX_NOTES,
            'routines' => 'nullable|array|max:' . self::MAX_PLAN_ROUTINES,
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }

        // Routines are exclusive — never mixed with projects/reminders/notes.
        $hasRoutines = ! empty($args['routines']);
        $hasLegacyTree = ! empty($args['project']['name']) || ! empty($args['subprojects']);
        $hasMulti = ! empty($args['projects']);
        $hasReminders = ! empty($args['reminders']);
        $hasNotes = ! empty($args['notes']);
        if ($hasRoutines && ($hasLegacyTree || $hasMulti || $hasReminders || $hasNotes)) {
            return $this->fail('A plan holds either routines or projects/reminders/notes, not both. Split into two plans.');
        }
        if ($hasLegacyTree && $hasMulti) {
            return $this->fail('Use either projects[] (multi-project) or project+subprojects (single tree), not both.');
        }
        if (! $hasLegacyTree && ! $hasMulti && ! $hasRoutines && ! $hasReminders && ! $hasNotes) {
            return $this->fail('The plan is empty: add a project with tasks, a reminder, a note, or at least one routine.');
        }

        if ($hasRoutines) {
            return $this->validatePlanRoutines($args['title'], $args['routines']);
        }

        $cleanTasks = function ($tasks, string $where) {
            $tasks = is_array($tasks) ? array_values($tasks) : [];
            $out = [];
            foreach ($tasks as $t) {
                if (! is_array($t)) {
                    return $this->fail("A task in {$where} is malformed.");
                }
                $t['subtasks'] = $t['subTasks'] ?? $t['subtasks'] ?? [];
                unset($t['subTasks']);
                $tv = Validator::make($t, [
                    'title' => 'required|string|max:120',
                    'due_date' => 'nullable|date',
                    'priority' => 'nullable|in:low,medium,high',
                    'description' => 'nullable|string|max:500',
                    'subtasks' => 'nullable|array',
                    'subtasks.*.title' => 'required|string|max:120',
                ]);
                if ($tv->fails()) {
                    return $this->fail("{$where}: " . $tv->errors()->first());
                }
                $subs = [];
                foreach ((array) ($t['subtasks'] ?? []) as $s) {
                    $subs[] = ['title' => trim((string) $s['title'])];
                }
                $out[] = [
                    'title' => trim((string) $t['title']),
                    'due_date' => $t['due_date'] ?? null,
                    'priority' => $t['priority'] ?? 'medium',
                    'description' => $t['description'] ?? null,
                    'subtasks' => $subs,
                ];
            }

            return $out;
        };

        // Normalize every root project to {name, description, tasks[], subprojects[], members[]}.
        $cleanMembers = function ($members) {
            $out = [];
            foreach (array_values((array) ($members ?? [])) as $m) {
                $m = trim((string) $m);
                if ($m !== '') {
                    $out[] = mb_substr($m, 0, 255);
                }
            }

            return array_values(array_unique($out));
        };
        $roots = [];
        if ($hasMulti) {
            foreach ($args['projects'] as $p) {
                if (! is_array($p) || trim((string) ($p['name'] ?? '')) === '') {
                    return $this->fail('Every project in projects[] needs a name.');
                }
                $tasks = $cleanTasks($p['tasks'] ?? [], "project '{$p['name']}'");
                if (isset($tasks['ok'])) {
                    return $tasks;
                }
                $roots[] = [
                    'name' => trim((string) $p['name']),
                    'description' => $p['description'] ?? null,
                    'tasks' => $tasks,
                    'subprojects' => [],
                    'members' => $cleanMembers($p['members'] ?? []),
                ];
            }
        } else {
            if (empty($args['project']['name'])) {
                // Reminders/notes-only plan (no project tree at all).
                $roots = [];
            } else {
                $direct = $cleanTasks($args['project']['tasks'] ?? [], 'project tasks');
                if (isset($direct['ok'])) {
                    return $direct;
                }
                $subs = [];
                foreach (array_values($args['subprojects']) as $si => $sub) {
                    $tasks = $cleanTasks($sub['tasks'] ?? [], "sub-project '{$sub['name']}'");
                    if (isset($tasks['ok'])) {
                        return $tasks;
                    }
                    $subs[] = [
                        'name' => trim((string) $sub['name']),
                        'description' => $sub['description'] ?? null,
                        'tasks' => $tasks,
                    ];
                }
                $roots[] = [
                    'name' => trim((string) $args['project']['name']),
                    'description' => $args['project']['description'] ?? null,
                    'tasks' => $direct,
                    'subprojects' => $subs,
                    'members' => $cleanMembers($args['project']['members'] ?? []),
                ];
            }
        }

        $taskCount = 0;
        $subCount = 0;
        $subProjectCount = 0;
        foreach ($roots as $r) {
            $taskCount += count($r['tasks']);
            foreach ($r['tasks'] as $t) {
                $subCount += count($t['subtasks']);
            }
            $subProjectCount += count($r['subprojects']);
            foreach ($r['subprojects'] as $s) {
                $taskCount += count($s['tasks']);
                foreach ($s['tasks'] as $t) {
                    $subCount += count($t['subtasks']);
                }
            }
        }

        $reminders = $this->cleanPlanReminders($args['reminders']);
        if (isset($reminders['ok'])) {
            return $reminders;
        }
        $notes = $this->cleanPlanNotes($args['notes']);
        if (isset($notes['ok'])) {
            return $notes;
        }

        if ($taskCount < 1 && empty($roots) && empty($reminders) && empty($notes)) {
            return $this->fail('The plan is empty: add a project (with tasks), a reminder, a note, or at least one task.');
        }
        if ($taskCount > \App\Models\AiPlan::MAX_TASKS) {
            return $this->fail('Too many tasks (max ' . \App\Models\AiPlan::MAX_TASKS . '). Split into smaller plans.');
        }
        if ($subCount > \App\Models\AiPlan::MAX_SUBTASKS) {
            return $this->fail('Too many subtasks (max ' . \App\Models\AiPlan::MAX_SUBTASKS . '). Split into smaller plans.');
        }

        // Legacy keys stay populated so old readers keep working; the
        // unified `projects` key is the source of truth for new code.
        $first = $roots[0] ?? null;
        $structure = [
            'projects' => $roots,
            'project' => $first ? [
                'name' => $first['name'],
                'description' => $first['description'],
                'tasks' => $first['tasks'],
            ] : null,
            'subprojects' => $first['subprojects'] ?? [],
            'routines' => [],
            'reminders' => $reminders,
            'notes' => $notes,
        ];

        return ['ok' => true, 'error' => null, 'resolved' => [
            'title' => trim((string) $args['title']),
            'structure' => $structure,
            'totals' => [
                'projects' => count($roots),
                'subprojects' => $subProjectCount,
                'tasks' => $taskCount,
                'subtasks' => $subCount,
                'routines' => 0,
                'steps' => 0,
                'reminders' => count($reminders),
                'notes' => count($notes),
                'members' => array_sum(array_map(fn ($r) => count($r['members'] ?? []), $roots)),
            ],
        ]];
    }

    /**
     * Clean plan-level reminders (same rules as reminder_create + location).
     * Returns list or ['ok'=>false,...] on failure.
     */
    private function cleanPlanReminders(array $reminders): array
    {
        $out = [];
        foreach (array_values($reminders) as $r) {
            if (! is_array($r)) {
                return $this->fail('A reminder in the plan is malformed.');
            }
            $rv = Validator::make($r, [
                'title' => 'required|string|max:255',
                'date' => 'nullable|date',
                'time' => 'nullable|date_format:H:i',
                'priority' => 'nullable|in:low,medium,high,urgent',
                'description' => 'nullable|string',
                'location' => 'nullable|string|max:255',
            ]);
            if ($rv->fails()) {
                return $this->fail('Reminder: ' . $rv->errors()->first());
            }
            $out[] = [
                'title' => trim((string) $r['title']),
                'date' => $r['date'] ?? null,
                'time' => $r['time'] ?? null,
                'priority' => $r['priority'] ?? 'medium',
                'description' => $r['description'] ?? null,
                'location' => isset($r['location']) ? trim((string) $r['location']) : null,
            ];
        }

        return $out;
    }

    /**
     * Clean plan-level notes (same rules as note_create).
     */
    private function cleanPlanNotes(array $notes): array
    {
        $out = [];
        foreach (array_values($notes) as $n) {
            if (! is_array($n)) {
                return $this->fail('A note in the plan is malformed.');
            }
            $nv = Validator::make($n, [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'category' => 'nullable|string|max:100',
            ]);
            if ($nv->fails()) {
                return $this->fail('Note: ' . $nv->errors()->first());
            }
            $out[] = [
                'title' => trim((string) $n['title']),
                'content' => (string) $n['content'],
                'category' => $n['category'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Validate a workout_plan_propose call WITHOUT writing anything.
     * Caps: 7 days, 40 exercises per day. Days arrive in execution order
     * (index 0 = DAY 1) so the caller can map DAY 1 to start_date.
     */
    public function validateWorkoutPlanPropose(array $args): array
    {
        $v = Validator::make($args, [
            'title' => 'required|string|max:160',
            'week_number' => 'nullable|integer|min:1|max:999',
            'start_date' => 'nullable|date',
            'goal' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'rules' => 'nullable|array|max:30',
            'rules.*' => 'nullable|string|max:500',
            'days' => 'required|array|min:1|max:7',
            'days.*.title' => 'required|string|max:120',
            'days.*.type' => 'required|in:training,rest,recovery',
            'days.*.notes' => 'nullable|string|max:2000',
            'days.*.exercises' => 'nullable|array|max:40',
            'days.*.exercises.*.name' => 'required|string|max:120',
            'days.*.exercises.*.section' => 'nullable|in:warmup,main,accessory,cooldown',
            'days.*.exercises.*.target_sets' => 'nullable|integer|min:1|max:99',
            'days.*.exercises.*.rep_min' => 'nullable|integer|min:1|max:999',
            'days.*.exercises.*.rep_max' => 'nullable|integer|min:1|max:999',
            'days.*.exercises.*.duration_seconds' => 'nullable|integer|min:1|max:86400',
            'days.*.exercises.*.target_weight' => 'nullable|numeric|min:0|max:10000',
            'days.*.exercises.*.target_rir' => 'nullable|numeric|min:0|max:10',
            'days.*.exercises.*.rest_seconds' => 'nullable|integer|min:0|max:3600',
            'days.*.exercises.*.tempo' => 'nullable|string|max:40',
            'days.*.exercises.*.side_mode' => 'nullable|in:bilateral,per_side,alternating',
            'days.*.exercises.*.is_amrap' => 'nullable|boolean',
            'days.*.exercises.*.is_circuit' => 'nullable|boolean',
            'days.*.exercises.*.circuit_rounds' => 'nullable|integer|min:1|max:30',
            'days.*.exercises.*.circuit_rest_seconds' => 'nullable|integer|min:0|max:3600',
            'days.*.exercises.*.notes' => 'nullable|string|max:2000',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }

        $days = array_values($args['days']);
        $exerciseCount = 0;
        foreach ($days as $dayIndex => $day) {
            foreach ((array) ($day['exercises'] ?? []) as $exerciseIndex => $exercise) {
                $exerciseCount++;
                $min = $exercise['rep_min'] ?? null;
                $max = $exercise['rep_max'] ?? null;
                if ($min !== null && $max !== null && (int) $max < (int) $min) {
                    return $this->fail("Day ".($dayIndex + 1)." exercise ".($exerciseIndex + 1).": max reps must be >= min reps.");
                }
            }
        }

        $resolved = [
            'title' => trim((string) $args['title']),
            'week_number' => isset($args['week_number']) ? (int) $args['week_number'] : null,
            'start_date' => $args['start_date'] ?? null,
            'goal' => isset($args['goal']) ? trim((string) $args['goal']) : null,
            'description' => isset($args['description']) ? trim((string) $args['description']) : null,
            'rules' => collect($args['rules'] ?? [])->map(fn ($rule) => trim((string) $rule))->filter()->values()->all(),
            'days' => $days,
        ];

        return ['ok' => true, 'error' => null, 'resolved' => [
            'title' => $resolved['title'],
            'structure' => $resolved,
            'totals' => [
                'days' => count($days),
                'exercises' => $exerciseCount,
            ],
        ]];
    }

    public function previewWorkoutPlan(array $resolved): array
    {
        $structure = $resolved['structure'] ?? $resolved;
        $days = array_values($structure['days'] ?? []);
        $rows = [
            ['k' => 'title', 'v' => (string) ($structure['title'] ?? '')],
            ['k' => 'days', 'v' => (string) count($days)],
            ['k' => 'exercises', 'v' => (string) array_sum(array_map(fn ($day) => count((array) ($day['exercises'] ?? [])) , $days))],
        ];
        if (! empty($structure['week_number'])) {
            $rows[] = ['k' => 'week_number', 'v' => (string) $structure['week_number']];
        }
        if (! empty($structure['start_date'])) {
            $rows[] = ['k' => 'start_date', 'v' => (string) $structure['start_date']];
        }

        return ['title' => 'Build workout plan', 'rows' => $rows];
    }

    /**
     * Validate the routines branch of a plan by reusing routine_create rules.
     */
    private function validatePlanRoutines(string $title, array $routines): array
    {
        $clean = [];
        foreach (array_values($routines) as $r) {
            if (! is_array($r)) {
                return $this->fail('A routine in the plan is malformed.');
            }
            $check = $this->validateRoutineCreate($r);
            if (! ($check['ok'] ?? false)) {
                return $this->fail('Routine: ' . ($check['error'] ?? 'invalid.'));
            }
            if (count($check['resolved']['steps']) > self::MAX_PLAN_ROUTINE_STEPS) {
                return $this->fail('Too many steps in one routine (max ' . self::MAX_PLAN_ROUTINE_STEPS . ').');
            }
            $clean[] = $check['resolved'];
        }

        $structure = ['projects' => [], 'project' => null, 'subprojects' => [], 'routines' => $clean, 'reminders' => [], 'notes' => []];

        return ['ok' => true, 'error' => null, 'resolved' => [
            'title' => trim($title),
            'structure' => $structure,
            'totals' => [
                'projects' => 0,
                'subprojects' => 0,
                'tasks' => 0,
                'subtasks' => 0,
                'routines' => count($clean),
                'steps' => array_sum(array_map(fn ($r) => count($r['steps']), $clean)),
                'reminders' => 0,
                'notes' => 0,
            ],
        ]];
    }

    /**
     * Root projects of a plan structure, unified across old single-tree
     * plans (project+subprojects) and new multi-project plans (projects[]).
     * Each root: {name, description, tasks[], subprojects[]}.
     */
    public function planRoots(array $structure): array
    {
        if (! empty($structure['projects'])) {
            return array_values($structure['projects']);
        }
        if (! empty($structure['project']['name'])) {
            return [[
                'name' => $structure['project']['name'],
                'description' => $structure['project']['description'] ?? null,
                'tasks' => $structure['project']['tasks'] ?? [],
                'subprojects' => $structure['subprojects'] ?? [],
            ]];
        }

        return [];
    }

    /**
     * Phase list for a validated structure. Empty phases are pre-marked done
     * so the stepper skips them.
     */
    public function buildPlanPhases(array $structure): array
    {
        if (! empty($structure['routines'])) {
            $total = count($structure['routines']);

            return [[
                'key' => 'routines', 'label' => 'Create routines', 'total' => $total, 'done' => 0,
                'status' => 'locked', 'result' => null,
            ]];
        }

        $roots = $this->planRoots($structure);
        $projectCount = count($roots);
        $subCount = array_sum(array_map(fn ($r) => count($r['subprojects'] ?? []), $roots));
        $taskCount = array_sum(array_map(fn ($r) => count($r['tasks'] ?? [])
            + array_sum(array_map(fn ($s) => count($s['tasks'] ?? []), $r['subprojects'] ?? [])), $roots));
        $subTaskCount = array_sum(array_map(fn ($r) => array_sum(array_map(fn ($t) => count($t['subtasks'] ?? []), $r['tasks'] ?? []))
            + array_sum(array_map(
                fn ($s) => array_sum(array_map(fn ($t) => count($t['subtasks'] ?? []), $s['tasks'] ?? [])),
                $r['subprojects'] ?? []
            )), $roots));
        $reminderCount = count($structure['reminders'] ?? []);
        $noteCount = count($structure['notes'] ?? []);

        $phase = fn ($key, $label, $total) => [
            'key' => $key, 'label' => $label, 'total' => $total, 'done' => 0,
            'status' => $total > 0 ? 'locked' : 'done', 'result' => null,
        ];

        // NOTE: legacy stored plans use key 'project'; new code accepts both.
        return [
            $phase('projects', $projectCount > 1 ? "Create {$projectCount} projects" : 'Create project', $projectCount),
            $phase('subprojects', 'Create sub-projects', $subCount),
            $phase('tasks', 'Create tasks', $taskCount),
            $phase('subtasks', 'Add subtasks', $subTaskCount),
            $phase('reminders', 'Create reminders', $reminderCount),
            $phase('notes', 'Create notes', $noteCount),
        ];
    }

    /**
     * Execute the CURRENT phase of a plan inside one transaction.
     * Returns ['ok'=>bool,'message'=>string,'phase'=>array].
     */
    public function executePlanPhase(\App\Models\AiPlan $plan, $user): array
    {
        return DB::transaction(function () use ($plan, $user) {
            $idx = $plan->current_phase;
            $phases = $plan->phases;
            $phase = $phases[$idx] ?? null;
            if (! $phase || ($phase['status'] ?? null) === 'done') {
                return ['ok' => false, 'message' => 'No pending phase.', 'phase' => $phase];
            }

            $structure = $plan->structure;
            $key = $phase['key'];
            $roots = $this->planRoots($structure);

            if ($key === 'routines') {
                $ids = [];
                foreach ($structure['routines'] as $r) {
                    $result = $this->execRoutineCreate($r, $user);
                    $ids[] = $result['id'];
                }
                $phase['result'] = ['routine_ids' => $ids];
                $message = count($ids) . ' routine(s) created.';
            } elseif ($key === 'project' || $key === 'projects') {
                // Legacy stored plans have a single root via project/subprojects.
                $created = [];
                $membersAdded = 0;
                $membersSkipped = [];
                foreach ($roots as $root) {
                    $project = $user->projects()->create([
                        'name' => $root['name'],
                        'description' => $root['description'] ?? null,
                        'status' => 'in_progress',
                        'type' => 'project',
                        'sort_order' => 0,
                    ]);
                    $created[] = ['id' => $project->id, 'name' => $project->name];
                    foreach (($root['members'] ?? []) as $ref) {
                        $member = $this->resolveMemberUser($ref);
                        if ($member && (int) $member->id !== (int) $user->id) {
                            $project->users()->syncWithoutDetaching([$member->id => ['role' => 'member']]);
                            $membersAdded++;
                        } else {
                            $membersSkipped[] = $ref;
                        }
                    }
                }
                if (empty($created)) {
                    return ['ok' => false, 'message' => 'Plan has no projects. Cancel and start over.', 'phase' => $phase];
                }
                $phase['result'] = [
                    'projects' => $created,
                    // Legacy compat for old readers.
                    'project_id' => $created[0]['id'],
                    'project_ids' => array_column($created, 'id'),
                    'project_name' => $created[0]['name'],
                ];
                $message = count($created) === 1
                    ? "Project '{$created[0]['name']}' created."
                    : count($created) . ' projects created.';
                if ($membersAdded > 0) {
                    $message .= " {$membersAdded} member(s) added.";
                }
                if (! empty($membersSkipped)) {
                    $message .= ' Skipped unknown users: ' . implode(', ', $membersSkipped) . '.';
                }
            } elseif ($key === 'subprojects') {
                $projectEntries = $phases[0]['result']['projects'] ?? null;
                if (! $projectEntries && isset($phases[0]['result']['project_id'])) {
                    $projectEntries = [['id' => $phases[0]['result']['project_id'], 'name' => $phases[0]['result']['project_name'] ?? '']];
                }
                if (! $projectEntries) {
                    return ['ok' => false, 'message' => 'Plan project is missing. Cancel and start over.', 'phase' => $phase];
                }
                $subsByRoot = [];
                $flatIds = [];
                foreach ($roots as $pi => $root) {
                    $parentId = $projectEntries[$pi]['id'] ?? $projectEntries[0]['id'];
                    $parent = Project::where('id', $parentId)->where('user_id', $user->id)->first();
                    if (! $parent) {
                        return ['ok' => false, 'message' => 'Plan project is missing. Cancel and start over.', 'phase' => $phase];
                    }
                    foreach (($root['subprojects'] ?? []) as $sub) {
                        $created = $user->projects()->create([
                            'name' => $sub['name'],
                            'description' => $sub['description'] ?? null,
                            'status' => 'in_progress',
                            'parent_id' => $parent->id,
                            'type' => 'project',
                            'sort_order' => 0,
                        ]);
                        $subsByRoot[$pi][] = $created->id;
                        $flatIds[] = $created->id;
                    }
                }
                $phase['result'] = ['subs' => $subsByRoot, 'sub_ids' => $flatIds];
                $message = count($flatIds) . ' sub-project(s) created.';
            } elseif ($key === 'tasks') {
                $projectEntries = $phases[0]['result']['projects'] ?? null;
                if (! $projectEntries && isset($phases[0]['result']['project_id'])) {
                    $projectEntries = [['id' => $phases[0]['result']['project_id'], 'name' => '']];
                }
                if (! $projectEntries) {
                    return ['ok' => false, 'message' => 'Plan project is missing. Cancel and start over.', 'phase' => $phase];
                }
                $subsByRoot = $phases[1]['result']['subs'] ?? null;
                if ($subsByRoot === null && isset($phases[1]['result']['sub_ids'])) {
                    // Legacy flat shape → belongs to the single root.
                    $subsByRoot = [0 => $phases[1]['result']['sub_ids']];
                }
                $taskIds = ['direct' => [], 'subs' => []];
                foreach ($roots as $pi => $root) {
                    $projectId = $projectEntries[$pi]['id'] ?? $projectEntries[0]['id'];
                    foreach (($root['tasks'] ?? []) as $t) {
                        $taskIds['direct'][$pi][] = $this->createPlanTask($user, $projectId, null, $t);
                    }
                    foreach (($root['subprojects'] ?? []) as $si => $sub) {
                        $subId = $subsByRoot[$pi][$si] ?? $projectId;
                        foreach (($sub['tasks'] ?? []) as $t) {
                            $taskIds['subs'][$pi][$si][] = $this->createPlanTask($user, $subId, null, $t);
                        }
                    }
                }
                $phase['result'] = ['task_ids' => $taskIds];
                $message = $phase['total'] . ' task(s) created.';
            } elseif ($key === 'subtasks') {
                $projectEntries = $phases[0]['result']['projects'] ?? null;
                if (! $projectEntries && isset($phases[0]['result']['project_id'])) {
                    $projectEntries = [['id' => $phases[0]['result']['project_id'], 'name' => '']];
                }
                // Tasks result index varies (legacy plans stored it at 2).
                $taskIds = null;
                foreach ($phases as $p) {
                    if (($p['key'] ?? null) === 'tasks' && isset($p['result']['task_ids'])) {
                        $taskIds = $p['result']['task_ids'];
                    }
                }
                if (! $projectEntries || ! $taskIds) {
                    return ['ok' => false, 'message' => 'Plan tasks are missing. Cancel and start over.', 'phase' => $phase];
                }
                $n = 0;
                if (empty($structure['projects'])) {
                    // Legacy stored plan: flat shapes, single root via
                    // project/subprojects keys. The tasks phase may have been
                    // (re-)executed by new code in nested shape — unwrap it.
                    $projectId = $projectEntries[0]['id'];
                    $subIds = $phases[1]['result']['sub_ids'] ?? [];
                    $directIds = $taskIds['direct'];
                    $subsIds = $taskIds['subs'];
                    if (isset($directIds[0]) && is_array($directIds[0])) {
                        $directIds = $directIds[0];
                        $subsIds = $subsIds[0] ?? [];
                    }
                    foreach (($structure['project']['tasks'] ?? []) as $ti => $t) {
                        foreach (($t['subtasks'] ?? []) as $s) {
                            $this->createPlanTask($user, $projectId, $directIds[$ti], ['title' => $s['title']]);
                            $n++;
                        }
                    }
                    foreach (($structure['subprojects'] ?? []) as $si => $sub) {
                        foreach (($sub['tasks'] ?? []) as $ti => $t) {
                            foreach (($t['subtasks'] ?? []) as $s) {
                                $this->createPlanTask($user, $subIds[$si] ?? $projectId, $subsIds[$si][$ti], ['title' => $s['title']]);
                                $n++;
                            }
                        }
                    }
                } else {
                    // New plans: nested per-root shapes direct[pi][ti],
                    // subs[pi][si][ti].
                    foreach ($roots as $pi => $root) {
                        $projectId = $projectEntries[$pi]['id'] ?? $projectEntries[0]['id'];
                        foreach (($root['tasks'] ?? []) as $ti => $t) {
                            $parentTaskId = $taskIds['direct'][$pi][$ti] ?? null;
                            if (! $parentTaskId) {
                                continue;
                            }
                            foreach (($t['subtasks'] ?? []) as $s) {
                                $this->createPlanTask($user, $projectId, $parentTaskId, ['title' => $s['title']]);
                                $n++;
                            }
                        }
                        foreach (($root['subprojects'] ?? []) as $si => $sub) {
                            $subProjectId = $phases[1]['result']['subs'][$pi][$si] ?? $projectId;
                            foreach (($sub['tasks'] ?? []) as $ti => $t) {
                                $parentTaskId = $taskIds['subs'][$pi][$si][$ti] ?? null;
                                if (! $parentTaskId) {
                                    continue;
                                }
                                foreach (($t['subtasks'] ?? []) as $s) {
                                    $this->createPlanTask($user, $subProjectId, $parentTaskId, ['title' => $s['title']]);
                                    $n++;
                                }
                            }
                        }
                    }
                }
                $phase['result'] = ['created' => $n];
                $message = $n . ' subtask(s) added.';
            } elseif ($key === 'reminders') {
                $ids = [];
                foreach (($structure['reminders'] ?? []) as $r) {
                    $rem = $user->reminders()->create([
                        'title' => $r['title'],
                        'date' => $r['date'] ?? null,
                        'time' => $r['time'] ?? null,
                        'priority' => $r['priority'] ?? 'medium',
                        'description' => $r['description'] ?? '',
                        'location' => $r['location'] ?? null,
                        'recurrence_type' => Reminder::RECURRENCE_NONE,
                        'recurrence_interval' => 1,
                    ]);
                    $ids[] = $rem->id;
                }
                $phase['result'] = ['reminder_ids' => $ids];
                $message = count($ids) . ' reminder(s) created.';
            } elseif ($key === 'notes') {
                $ids = [];
                foreach (($structure['notes'] ?? []) as $n) {
                    $note = $user->notes()->create([
                        'title' => $n['title'],
                        'content' => $n['content'],
                        'category' => $n['category'] ?? null,
                    ]);
                    $ids[] = $note->id;
                }
                $phase['result'] = ['note_ids' => $ids];
                $message = count($ids) . ' note(s) created.';
            } else {
                return ['ok' => false, 'message' => 'Unknown plan phase.', 'phase' => $phase];
            }

            $phase['done'] = $phase['total'];
            $phase['status'] = 'done';
            $phases[$idx] = $phase;

            // Advance past already-done (e.g. zero-count) phases.
            $next = $idx + 1;
            $plan->phases = $phases;
            $plan->current_phase = $next;
            $plan->status = \App\Models\AiPlan::STATUS_EXECUTING;
            $finished = true;
            foreach ($phases as $p) {
                if (($p['status'] ?? null) !== 'done') {
                    $finished = false;
                    break;
                }
            }
            if ($finished) {
                $plan->status = \App\Models\AiPlan::STATUS_DONE;
                $plan->executed_at = now();
                $message .= ' Plan complete ✅';
            }
            $plan->touchExpiry();
            $plan->save();

            return ['ok' => true, 'message' => $message, 'phase' => $phase];
        });
    }

    private function createPlanTask($user, int $projectId, ?int $parentId, array $t): int
    {
        $task = $user->tasks()->create([
            'project_id' => $projectId,
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'title' => $t['title'],
            'description' => $t['description'] ?? null,
            'due_date' => $t['due_date'] ?? null,
            'priority' => $t['priority'] ?? 'medium',
            'status' => 'to_do',
        ]);

        return $task->id;
    }

    // ── previews ──

    private function previewTaskDelete(array $resolved): array
    {
        $task = Task::withCount('children')->find($resolved['id']);
        $subs = $task?->children_count ?? 0;

        return [
            'title' => 'Delete task',
            'danger' => true,
            'rows' => [['k' => 'Task', 'v' => $resolved['title'] ?? "#{$resolved['id']}"]],
            'impact' => $subs > 0 ? "{$subs} subtask(s) will also be deleted." : null,
        ];
    }

    private function previewRoutineDelete(array $resolved): array
    {
        $routine = Routine::find($resolved['id']);
        $count = $routine ? $routine->completions()->count() : 0;

        return [
            'title' => 'Delete routine',
            'danger' => true,
            'rows' => [['k' => 'Routine', 'v' => $resolved['title'] ?? "#{$resolved['id']}"]],
            'impact' => $count > 0 ? "Hides the routine; {$count} recorded completion(s) are kept as history." : 'Hides the routine.',
        ];
    }

    /**
     * Tree preview for a plan (rendered as the structure card, no DB writes).
     */
    public function previewPlan(array $resolved): array
    {
        $structure = $resolved['structure'];
        $totals = $resolved['totals'];
        $taskView = fn ($t) => [
            'title' => $t['title'],
            'due_date' => $t['due_date'] ?? null,
            'subtasks' => array_map(fn ($s) => is_array($s) ? $s['title'] : $s, $t['subtasks'] ?? []),
        ];
        $roots = $this->planRoots($structure);
        $tree = [
            'projects' => array_map(fn ($r) => [
                'name' => $r['name'],
                'tasks' => array_map($taskView, $r['tasks'] ?? []),
                'subprojects' => array_map(fn ($s) => [
                    'name' => $s['name'],
                    'tasks' => array_map($taskView, $s['tasks'] ?? []),
                ], $r['subprojects'] ?? []),
                'members' => array_values($r['members'] ?? []),
            ], $roots),
            // Legacy keys for old frontend readers.
            'project' => ! empty($structure['project']) ? [
                'name' => $structure['project']['name'],
                'tasks' => array_map($taskView, $structure['project']['tasks'] ?? []),
            ] : null,
            'subprojects' => array_map(fn ($s) => [
                'name' => $s['name'],
                'tasks' => array_map($taskView, $s['tasks'] ?? []),
            ], $structure['subprojects'] ?? []),
            'routines' => array_map(fn ($r) => [
                'title' => $r['title'],
                'frequency' => $r['frequency'],
                'tracking_mode' => $r['tracking_mode'] ?? 'none',
                'steps' => array_map(fn ($s) => $s['name'], $r['steps'] ?? []),
            ], $structure['routines'] ?? []),
            'reminders' => array_map(fn ($r) => [
                'title' => $r['title'],
                'date' => $r['date'] ?? null,
                'time' => $r['time'] ?? null,
                'location' => $r['location'] ?? null,
            ], $structure['reminders'] ?? []),
            'notes' => array_map(fn ($n) => [
                'title' => $n['title'],
                'category' => $n['category'] ?? null,
            ], $structure['notes'] ?? []),
        ];

        return [
            'title' => $resolved['title'],
            'tree' => $tree,
            'totals' => $totals,
        ];
    }

    // ── executors ──

    private function execTaskCreate(array $r, $user): array
    {
        $task = $user->tasks()->create([
            'project_id' => $r['project_id'],
            'user_id' => $user->id,
            'parent_id' => $r['parent_id'] ?? null,
            'title' => $r['title'],
            'description' => $r['description'] ?? null,
            'due_date' => $r['due_date'] ?? null,
            'priority' => $r['priority'],
            'status' => $r['status'],
        ]);

        $msg = isset($r['parent_id'])
            ? "Subtask '{$task->title}' created under '{$r['parent_title']}'."
            : "Task '{$task->title}' created.";

        return ['ok' => true, 'message' => $msg, 'id' => $task->id];
    }

    private function execTaskUpdate(array $r, $user): array
    {
        $task = Task::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $data = array_intersect_key($r, array_flip(['title', 'due_date', 'priority', 'status', 'description']));
        if (isset($data['status'])) {
            $data['completed_at'] = $data['status'] === 'completed' ? ($task->completed_at ?? now()) : null;
        }
        $task->update($data);

        return ['ok' => true, 'message' => "Task '{$task->title}' updated.", 'id' => $task->id];
    }

    private function execTaskComplete(array $r, $user): array
    {
        $task = Task::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $task->update(['status' => 'completed', 'completed_at' => $task->completed_at ?? now()]);

        return ['ok' => true, 'message' => "Task '{$task->title}' completed.", 'id' => $task->id];
    }

    private function execTaskDelete(array $r, $user): array
    {
        $task = Task::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $title = $task->title;
        $task->delete();

        return ['ok' => true, 'message' => "Task '{$title}' deleted.", 'id' => null];
    }

    private function execReminderCreate(array $r, $user): array
    {
        // reminders.description is NOT NULL in the schema — normalize nulls.
        $rem = $user->reminders()->create(array_merge($r, [
            'description' => $r['description'] ?? '',
            'recurrence_type' => Reminder::RECURRENCE_NONE, 'recurrence_interval' => 1,
        ]));

        return ['ok' => true, 'message' => "Reminder '{$rem->title}' created.", 'id' => $rem->id];
    }

    private function execReminderComplete(array $r, $user): array
    {
        $rem = Reminder::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $rem->markAsCompleted();

        return ['ok' => true, 'message' => "Reminder '{$rem->title}' completed.", 'id' => $rem->id];
    }

    private function execDelete(array $r, $user, string $model, string $label): array
    {
        $row = $model::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $title = $row->title ?? $row->name ?? "#{$row->id}";
        $row->delete();

        return ['ok' => true, 'message' => "{$label} '{$title}' deleted.", 'id' => null];
    }

    private function execNoteCreate(array $r, $user): array
    {
        $note = $user->notes()->create($r);

        return ['ok' => true, 'message' => "Note '{$note->title}' created.", 'id' => $note->id];
    }

    private function execNoteUpdate(array $r, $user): array
    {
        $note = Note::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $note->update(array_intersect_key($r, array_flip(['title', 'content', 'category'])));

        return ['ok' => true, 'message' => "Note '{$note->title}' updated.", 'id' => $note->id];
    }

    private function execProjectCreate(array $r, $user): array
    {
        $project = $user->projects()->create([
            'name' => $r['name'],
            'description' => $r['description'] ?? null,
            'status' => $r['status'] ?? 'not_started',
            'parent_id' => $r['parent_id'] ?? null,
            'type' => 'project',
            'sort_order' => 0,
        ]);

        $msg = isset($r['parent_id'])
            ? "Sub-project '{$project->name}' created under '{$r['parent_name']}'."
            : "Project '{$project->name}' created.";

        return ['ok' => true, 'message' => $msg, 'id' => $project->id];
    }

    private function execProjectAddMember(array $r, $user): array
    {
        $project = Project::where('id', $r['project_id'])->where('user_id', $user->id)->firstOrFail();
        $member = User::findOrFail($r['member_id']);

        $already = $project->users()->where('users.id', $member->id)->exists();
        // syncWithoutDetaching keeps existing pivot rows (dedupe-safe).
        $project->users()->syncWithoutDetaching([$member->id => ['role' => $r['role'] ?? 'member']]);

        $msg = $already
            ? "'{$member->name}' is already on '{$project->name}' (role updated to '{$r['role']}')."
            : "'{$member->name}' added to '{$project->name}' as '{$r['role']}'.";

        return ['ok' => true, 'message' => $msg, 'id' => $project->id];
    }

    private function execNoteLink(array $r, $user): array
    {
        $note = Note::where('id', $r['note_id'])->where('user_id', $user->id)->firstOrFail();
        $link = app(NoteLinkService::class)->attach($note, $r['target_type'], (int) $r['target_id']);
        if (! $link) {
            return ['ok' => false, 'message' => 'Could not create the link (target missing or not yours).', 'id' => null];
        }

        return ['ok' => true, 'message' => "Note '{$r['note_title']}' linked to {$r['target_kind']} '{$r['target_title']}'.", 'id' => $link->id];
    }

    /**
     * Read-only summary: computes the unified report and returns it as the
     * confirmation message. Writes nothing to the DB.
     */
    private function execReportGenerate(array $r, $user): array
    {
        $range = ReportRange::fromRequest($r['range'], null, null);
        $report = UnifiedReportService::overview($user->id, $range);

        $lines = ["📊 Workspace report — {$range->label} ({$range->from->toDateString()} → {$range->to->toDateString()})", ''];
        $t = $report['tasks'];
        $lines[] = "Tasks: {$t['completed']}/{$t['created']} completed ({$t['rate']}%)"
            . ($t['overdue_now'] > 0 ? " · ⚠️ {$t['overdue_now']} overdue" : '')
            . ($t['avg_hours'] !== null ? " · avg {$t['avg_hours']}h/task" : '');
        $rt = $report['routines'];
        $lines[] = 'Routines: ' . ($rt['total'] ?? count($rt['rows'] ?? [])) . ' tracked'
            . (isset($rt['avg_rate']) ? " · avg adherence {$rt['avg_rate']}%" : '');
        $tm = $report['time'];
        $lines[] = 'Focus time: ' . $this->formatSeconds($tm['total'] ?? 0)
            . ' (Δ ' . $this->formatSignedSeconds($report['deltas']['time'] ?? 0) . ' vs previous)';
        $w = $report['workouts'];
        $lines[] = "Workouts: {$w['completed']} completed";
        $d = $report['deltas'];
        $trend = $d['tasks_completed'] ?? 0;
        $lines[] = 'Trend: ' . ($trend >= 0 ? '+' : '') . $trend . ' tasks vs previous ' . $range->preset;
        $insights = array_slice($report['insights'] ?? [], 0, 4);
        if ($insights) {
            $lines[] = '';
            $lines[] = 'Top insights:';
            foreach ($insights as $i) {
                $lines[] = '- ' . ($i['title'] ?? '') . ' ' . ($i['body'] ?? '');
            }
        }
        $lines[] = '';
        $lines[] = "Open the full charts at Reports → {$range->label}.";

        return ['ok' => true, 'message' => implode("\n", $lines), 'id' => null];
    }

    private function formatSeconds(int|float $seconds): string
    {
        $seconds = (int) $seconds;
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($h > 0) {
            return "{$h}h {$m}m";
        }

        return "{$m}m";
    }

    private function formatSignedSeconds(int|float $seconds): string
    {
        $seconds = (int) $seconds;
        $sign = $seconds >= 0 ? '+' : '−';

        return $sign . $this->formatSeconds(abs($seconds));
    }

    private function execChecklistAdd(array $r, $user): array
    {
        $task = Task::where('id', $r['task_id'])->where('user_id', $user->id)->firstOrFail();
        $item = $task->checklistItems()->create(['name' => $r['name']]);

        return ['ok' => true, 'message' => "Checklist '{$item->name}' added.", 'id' => $item->id];
    }

    private function execChecklistToggle(array $r, $user): array
    {
        $item = ChecklistItem::where('id', $r['id'])->whereHas('task', fn ($q) => $q->where('user_id', $user->id))->firstOrFail();
        $item->update(['completed' => ! $item->completed]);

        return ['ok' => true, 'message' => "Checklist '{$item->name}' " . ($item->completed ? 'done.' : 'reopened.'), 'id' => $item->id];
    }

    private function execRoutineCreate(array $r, $user): array
    {
        $periodKeys = array_keys(config('routines.periods', []));
        $routine = $user->routines()->create([
            'title' => $r['title'],
            'description' => $r['description'] ?? null,
            'frequency' => $r['frequency'],
            'days' => $r['days'],
            'month_days' => $r['month_days'],
            'every_n_days' => $r['every_n_days'],
            'weeks' => null,
            'months' => null,
            'time_period' => ($r['time_period'] && in_array($r['time_period'], $periodKeys, true)) ? $r['time_period'] : null,
            'behavior_type' => in_array($r['behavior_type'] ?? 'build', ['build', 'avoid'], true) ? $r['behavior_type'] : 'build',
            'count_violations' => ! empty($r['count_violations']),
            'tracking_mode' => $r['tracking_mode'] ?? 'none',
            'value_kind' => $r['value_kind'] ?? null,
            'value_unit' => $r['value_unit'] ?? null,
            'value_label' => $r['value_label'] ?? null,
        ]);

        $n = 0;
        foreach ((array) ($r['steps'] ?? []) as $s) {
            $routine->checklistItems()->create([
                'user_id' => $user->id,
                'name' => $s['name'],
                'sort_order' => $s['sort_order'] ?? $n,
                'target_sets' => $s['target_sets'] ?? 1,
                'unit' => $s['unit'] ?? null,
            ]);
            $n++;
        }

        $msg = "Routine '{$routine->title}' created ({$routine->recurrenceLabel()})";
        if ($n > 0) {
            $msg .= " with {$n} step(s)";
        }
        if (($r['tracking_mode'] ?? 'none') !== 'none') {
            $msg .= " [tracking: {$r['tracking_mode']}]";
        }

        return ['ok' => true, 'message' => $msg . '.', 'id' => $routine->id];
    }

    private function execRoutineComplete(array $r, $user): array
    {
        $routine = Routine::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        if ($routine->isAvoid()) {
            return ['ok' => false, 'message' => "Routine '{$routine->title}' is an avoid habit and cannot be checked off — staying clean is the goal.", 'id' => $routine->id];
        }
        if ($routine->completedOn($r['date'])) {
            return ['ok' => true, 'message' => "Routine '{$routine->title}' is already done for {$r['date']}.", 'id' => $routine->id];
        }
        RoutineCompletion::create([
            'user_id' => $user->id,
            'routine_id' => $routine->id,
            'completed_date' => $r['date'],
            'completed_at' => now(),
        ]);

        return ['ok' => true, 'message' => "Routine '{$routine->title}' marked done for {$r['date']}.", 'id' => $routine->id];
    }

    private function execRoutineDelete(array $r, $user): array
    {
        $routine = Routine::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $title = $routine->title;
        $routine->delete();

        return ['ok' => true, 'message' => "Routine '{$title}' deleted.", 'id' => null];
    }

    private function previewRoutineLog(array $resolved): array
    {
        $rows = [
            ['k' => 'Routine', 'v' => $resolved['routine_title']],
            ['k' => 'Date', 'v' => $resolved['date']],
            ['k' => 'Value', 'v' => ($resolved['value_display'] ?? (string) $resolved['value']) . ($resolved['unit'] ? ' ' . $resolved['unit'] : '')],
        ];
        if ($resolved['item_name']) {
            $rows[] = ['k' => 'Step', 'v' => $resolved['item_name'] . ' (set ' . $resolved['set_no'] . ')'];
        }

        return ['title' => 'Log value', 'rows' => $rows];
    }

    private function execRoutineLog(array $r, $user): array
    {
        $routine = Routine::where('id', $r['routine_id'])->where('user_id', $user->id)->firstOrFail();
        \App\Models\RoutineLog::logValue($user->id, $routine->id, $r['date'], $r['value'], $r['item_id'], $r['set_no']);

        $what = $r['item_name'] ? "'{$r['item_name']}' set {$r['set_no']}" : "'{$routine->title}'";
        $shown = $r['value_display'] ?? (string) $r['value'];
        $msg = "Logged {$shown}" . (($r['unit'] ?? null) && ! ($r['is_time'] ?? false) ? " {$r['unit']}" : '') . " for {$what} on {$r['date']}.";

        // Value mode has one input per day: logging it completes the routine.
        if ($r['tracking_mode'] === Routine::TRACKING_VALUE && ! $routine->completedOn($r['date'])) {
            $routine->toggleOn($r['date']);
            $msg .= " Routine completed ✅";
        }

        // Sets mode auto-completes the routine when every target set is logged.
        if ($r['tracking_mode'] === Routine::TRACKING_SETS && ! $routine->completedOn($r['date'])) {
            $items = $routine->checklistItems()->get();
            $logs = \App\Models\RoutineLog::where('user_id', $user->id)
                ->where('routine_id', $routine->id)
                ->where('completed_date', $r['date'])
                ->whereNotNull('checklist_item_id')
                ->get()
                ->groupBy('checklist_item_id');
            $allDone = $items->isNotEmpty();
            foreach ($items as $item) {
                $have = isset($logs[$item->id]) ? $logs[$item->id]->pluck('set_no')->map(fn ($n) => (int) $n)->all() : [];
                for ($s = 1; $s <= max(1, (int) $item->target_sets); $s++) {
                    if (! in_array($s, $have, true)) {
                        $allDone = false;
                        break 2;
                    }
                }
            }
            if ($allDone) {
                $routine->toggleOn($r['date']);
                $msg .= " All sets done — routine completed ✅";
            }
        }

        return ['ok' => true, 'message' => $msg, 'id' => $routine->id];
    }

    // ── helpers ──

    /**
     * Provider-safe tool names use underscores (dots/dashes are rejected by
     * OpenAI-compatible APIs). Old pending rows may still hold dotted names.
     */
    public static function normalizeToolName(string $tool): string
    {
        return str_replace(['.', '-'], '_', trim($tool));
    }

    private function normalize(array $args): array
    {
        // Models (esp. smaller ones via OpenRouter) often send camelCase keys
        // despite the schema. Map the common ones before validating.
        $aliases = [
            'projectId' => 'project_id',
            'taskId' => 'task_id',
            'task_id_' => 'task_id',
            'parentId' => 'parent_id',
            'dueDate' => 'due_date',
            'due_date_' => 'due_date',
            'startDate' => 'start_date',
            'endDate' => 'end_date',
            'isRecurring' => 'is_recurring',
            'recurrenceType' => 'recurrence_type',
            'recurrenceInterval' => 'recurrence_interval',
            'isCompleted' => 'is_completed',
            'isFavorite' => 'is_favorite',
            'monthDays' => 'month_days',
            'month_day' => 'month_days',
            'day' => 'days',
            'everyNDays' => 'every_n_days',
            'every_n_day' => 'every_n_days',
            'timePeriod' => 'time_period',
            'routineId' => 'routine_id',
            'itemId' => 'item_id',
            'setNo' => 'set_no',
        ];
        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $args) && ! array_key_exists($to, $args)) {
                $args[$to] = $args[$from];
            }
            unset($args[$from]);
        }

        // OpenRouter may send numbers as strings; keep strict but forgiving for ids.
        foreach (['id', 'task_id', 'project_id', 'routine_id', 'item_id'] as $k) {
            if (isset($args[$k]) && is_numeric($args[$k])) {
                $args[$k] = (int) $args[$k];
            }
        }

        return $args;
    }

    private function rows(array $resolved, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $resolved) && $resolved[$k] !== null && $resolved[$k] !== '') {
                $out[] = ['k' => $k, 'v' => (string) $resolved[$k]];
            }
        }

        return $out;
    }

    private function fail(string $error): array
    {
        return ['ok' => false, 'error' => $error, 'resolved' => []];
    }
}
