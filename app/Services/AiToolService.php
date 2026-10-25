<?php

namespace App\Services;

use App\Models\ChecklistItem;
use App\Models\Note;
use App\Models\Project;
use App\Services\AiTooling\ToolError;
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
    /**
     * Max collaborator candidates returned for an ambiguous name. Bounded so
     * the reference can never be used to enumerate the whole user table.
     */
    public const MEMBER_CANDIDATE_LIMIT = 5;

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
        'workspace_search', 'ask_user_choice',
    ];

    /**
     * Tool groups for the Intent Router (AiIntentRouter::toolFilter).
     * Subsets are attached on high-confidence routes only; anything else
     * keeps the full toolset (fail-open). Add new tools to a group here —
     * never gate execution on the router (ToolPipeline still validates).
     */
    public const TOOL_GROUPS = [
        'single' => [
            'task_create', 'task_update', 'task_complete', 'task_delete',
            'reminder_create', 'reminder_complete', 'reminder_delete',
            'note_create', 'note_update', 'note_delete',
            'project_create', 'project_add_member',
            'note_link',
            'checklist_add', 'checklist_toggle',
            'routine_create', 'routine_complete', 'routine_delete', 'routine_log',
        ],
        'report' => ['report_generate'],
        'plan' => ['plan_propose'],
        'workout' => ['workout_plan_propose'],
        'search' => ['workspace_search'],
        'interaction' => ['ask_user_choice'],
    ];

    public const ROUTINE_VALUE_KINDS = ['number', 'weight', 'time', 'reps', 'percent'];

    public const MAX_PLAN_ROUTINES = 7;
    public const MAX_PLAN_ROUTINE_STEPS = 20;

    public const WEEK_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * Security classification per tool.
     *
     * READ        — no state change (report_generate).
     * WRITE       — creates/updates scoped to the authenticated user; needs confirmation.
     * DESTRUCTIVE — deletes or completes (irreversible without Undo); needs confirmation + impact preview.
     * SENSITIVE   — shares data with other users or reads broadly; needs confirmation + explicit preview.
     *
     * The LLM can never change this map: it lives server-side and no tool
     * accepts a permission/policy argument (see AiSecurity::FORBIDDEN_ARG_KEYS).
     */
    public const TOOL_RISK = [
        'task_create' => ['WRITE'],
        'task_update' => ['WRITE'],
        'task_complete' => ['WRITE'],
        'task_delete' => ['WRITE', 'DESTRUCTIVE'],
        'reminder_create' => ['WRITE'],
        'reminder_complete' => ['WRITE'],
        'reminder_delete' => ['WRITE', 'DESTRUCTIVE'],
        'note_create' => ['WRITE'],
        'note_update' => ['WRITE'],
        'note_delete' => ['WRITE', 'DESTRUCTIVE'],
        'project_create' => ['WRITE'],
        'project_add_member' => ['WRITE', 'SENSITIVE'],
        'note_link' => ['WRITE'],
        'report_generate' => ['READ'],
        'checklist_add' => ['WRITE'],
        'checklist_toggle' => ['WRITE'],
        'routine_create' => ['WRITE'],
        'routine_complete' => ['WRITE'],
        'routine_delete' => ['WRITE', 'DESTRUCTIVE'],
        'routine_log' => ['WRITE'],
        'plan_propose' => ['WRITE', 'SENSITIVE'],
        'workout_plan_propose' => ['WRITE'],
        'workspace_search' => ['READ'],
        'ask_user_choice' => ['WRITE'],
    ];

    public static function riskOf(string $tool): array
    {
        $tool = self::normalizeToolName($tool);

        return self::TOOL_RISK[$tool] ?? ['WRITE'];
    }

    public static function isReadOnly(string $tool): bool
    {
        return self::riskOf($tool) === ['READ'];
    }

    public static function isDestructive(string $tool): bool
    {
        return in_array('DESTRUCTIVE', self::riskOf($tool), true);
    }

    public static function isSensitive(string $tool): bool
    {
        return in_array('SENSITIVE', self::riskOf($tool), true);
    }

    /** Every non-READ tool needs explicit user confirmation. No exceptions. */
    public static function requiresConfirmation(string $tool): bool
    {
        return ! self::isReadOnly($tool);
    }

    /**
     * OpenAI-compatible function definitions for OpenRouter/custom providers.
     * $only narrows to the given tool names (order-preserving, unknown
     * names ignored) — used by the Intent Router on high-confidence
     * routes. Null keeps the full set (default, backward compatible).
     */
    public function definitions(?array $only = null): array
    {
        $date = fn () => ['type' => 'string', 'description' => 'Date as YYYY-MM-DD'];

        $all = [
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
                'daily_goal_value' => ['type' => 'integer', 'description' => 'Daily minimum steps (optional, 1-10000)'],
                'daily_goal_unit' => ['type' => 'string', 'enum' => ['tasks', 'pages', 'minutes'], 'description' => 'Daily goal metric (default tasks)'],
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
                                        'duration_seconds' => ['type' => 'integer', 'description' => 'For timed holds like Plank 3x30-60s: set target_sets 3, duration_seconds 60 (max of range), reps null. Prefer duration over reps for holds.'],
                                        'target_weight' => ['type' => 'number'],
                                        'target_rir' => ['type' => 'number', 'description' => 'RIR target number. If the user states a range like "3–4" or "3 to 4", output the midpoint (e.g. 3.5) as a number. Never output a string range.'],
                                        'rest_seconds' => ['type' => 'integer', 'description' => 'REQUIRED on training days, never null. Main compounds 120-180 (upper bound), accessory compounds 90, isolation/core 60.'],
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
            $this->fn('workspace_search', 'Search the user\'s existing projects, tasks, notes, routines and reminders by name/title (Persian-friendly). ALWAYS use this before creating or attaching anything when the target may already exist — it returns ids and names you can pass as project_id/parent_id.', [
                'query' => ['type' => 'string', 'description' => 'Free-text search (name or title fragment, Persian or English)'],
                'kinds' => ['type' => 'array', 'description' => 'Optional filter: any of project, task, note, routine, reminder. Omit to search everything.', 'items' => ['type' => 'string', 'enum' => ['project', 'task', 'note', 'routine', 'reminder']]],
                'limit' => ['type' => 'integer', 'description' => 'Max results (default 8, max 20)'],
            ], ['query']),
            $this->fn('ask_user_choice', 'Ask the user to pick between 2-5 concrete options instead of guessing. Use when the target is ambiguous (e.g. several similar project names) or several structures are reasonable. Each option carries the exact tool + args to run for that choice — the user picks with one click and the chosen call is proposed normally.', [
                'question' => ['type' => 'string', 'description' => 'Short question shown to the user (Persian if they write Persian)'],
                'options' => [
                    'type' => 'array',
                    'description' => '2-5 options. Each: {label, tool, args}. tool must be a real tool name (never ask_user_choice); args is the exact argument object for that tool.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string', 'description' => 'Short button label (what will happen)'],
                            'tool' => ['type' => 'string', 'description' => 'Tool to run if this option is picked'],
                            'args' => ['type' => 'object', 'description' => 'Arguments for that tool'],
                        ],
                        'required' => ['label', 'tool'],
                        'additionalProperties' => false,
                    ],
                ],
            ], ['question', 'options']),
            [
                'type' => 'function',
                'function' => [
                    'name' => 'plan_propose',
                    'description' => 'Propose a whole multi-level build as ONE plan: up to 5 independent projects (projects[] array of {name, tasks[]}) each with tasks, OR one project with sub-projects (project object {name, tasks[]} + subprojects array), plus optional reminders[] and notes[]. Shapes: project is ALWAYS an object like {"name":"Task Manager","tasks":[{"title":"T1"}]}, NEVER a string. projects/subprojects are ALWAYS arrays of objects, tasks/subtasks ALWAYS arrays of {title}. If a project/sub-project ALREADY EXISTS (see PROJECTS in the context), set its project_id to attach the new tasks to that record — NEVER propose a duplicate of an existing project. NEVER use for workout/training plans — those must use workout_plan_propose.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => 'Short plan title'],
                            'project' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                    'project_id' => ['type' => 'integer', 'description' => 'ID of an EXISTING project (from the PROJECTS context) — attach instead of creating a duplicate'],
                                    'daily_goal_value' => ['type' => 'integer', 'description' => 'Daily minimum steps for the study project (optional, 1-10000)'],
                                    'daily_goal_unit' => ['type' => 'string', 'enum' => ['tasks', 'pages', 'minutes'], 'description' => 'Daily goal metric (default tasks)'],
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
                                        'project_id' => ['type' => 'integer', 'description' => 'ID of an EXISTING sub-project under the same parent — attach instead of creating'],
                                        'daily_goal_value' => ['type' => 'integer', 'description' => 'Daily minimum steps (optional, 1-10000)'],
                                        'daily_goal_unit' => ['type' => 'string', 'enum' => ['tasks', 'pages', 'minutes'], 'description' => 'Daily goal metric (default tasks)'],
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
                                        'project_id' => ['type' => 'integer', 'description' => 'ID of an EXISTING project (from the PROJECTS context) — attach instead of creating a duplicate'],
                                        'daily_goal_value' => ['type' => 'integer', 'description' => 'Daily minimum steps (optional, 1-10000)'],
                                        'daily_goal_unit' => ['type' => 'string', 'enum' => ['tasks', 'pages', 'minutes'], 'description' => 'Daily goal metric (default tasks)'],
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

        if ($only === null) {
            return $all;
        }
        $keep = array_fill_keys(array_map([self::class, 'normalizeToolName'], $only), true);

        return array_values(array_filter(
            $all,
            fn ($def) => isset($keep[self::normalizeToolName($def['function']['name'] ?? '')])
        ));
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
                'weight' => ['type' => 'number', 'description' => 'Study pages for this item (1 weight unit = 1 page, 0-99). Omit when unknown.'],
                'subtasks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => 'Max 120 chars'],
                            'weight' => ['type' => 'number', 'description' => 'Study pages (0-99). Omit when unknown.'],
                        ],
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
            return $this->fail("Unknown tool: {$tool}.", ToolError::UNKNOWN_TOOL);
        }

        // Security Boundary: the LLM/client can never smuggle identity,
        // permission, or confirmation-policy keys through tool args.
        // `user` is legitimate ONLY for project_add_member (member ref).
        // (The pipeline invokes the same screen as its own dedicated stage.)
        $forbidden = AiSecurity::findForbiddenKey($args);
        if ($forbidden !== null && ! ($tool === 'project_add_member' && strtolower($forbidden) === 'user')) {
            return $this->fail("Argument '{$forbidden}' is not allowed.", ToolError::AUTHORIZATION_ERROR);
        }

        $args = $this->normalizeArgs($args, $tool);

        return match ($tool) {
            'task_create' => $this->validateTaskCreate($args, $user),
            'task_update' => $this->validateTaskUpdate($args, $user),
            'task_complete' => $this->validateTaskComplete($args, $user),
            'task_delete' => $this->validateTaskDelete($args, $user),
            'reminder_create' => $this->validateReminderCreate($args),
            'reminder_complete' => $this->validateReminderComplete($args, $user),
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
            'plan_propose' => $this->validatePlanPropose($args, $user),
            'workout_plan_propose' => $this->validateWorkoutPlanPropose($args),
            'workspace_search' => $this->validateWorkspaceSearch($args),
            'ask_user_choice' => $this->validateAskUserChoice($args),
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
            'project_create' => ['title' => isset($resolved['parent_id']) ? 'Create sub-project' : 'Create project', 'rows' => $this->rows($resolved, ['name', 'parent_name', 'status', 'description', 'daily_goal_value', 'daily_goal_unit'])],
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
            'workspace_search' => ['title' => 'Search workspace', 'rows' => $this->rows($resolved, ['query', 'kinds_label', 'matches'])],
            'ask_user_choice' => [
                'title' => 'Question for you',
                'question' => $resolved['question'] ?? '',
                // Labels in order — the UI renders one button per entry and
                // posts its index back via /ai/actions/{id}/choice.
                'options' => array_values(array_map(fn ($o) => (string) $o['label'], $resolved['options'] ?? [])),
                'rows' => array_merge([['k' => 'question', 'v' => $resolved['question'] ?? '']],
                    array_map(fn ($o, $i) => ['k' => 'option '.($i + 1), 'v' => (string) $o['label']], $resolved['options'] ?? [], array_keys($resolved['options'] ?? []))),
            ],
            default => ['title' => ucfirst(str_replace('_', ' ', $tool)), 'rows' => $this->rows($resolved, array_keys($resolved))],
        };
    }

    /**
     * Execute a validated pending action inside a transaction.
     * Returns a STRUCTURED result ['ok','code','message','id'?] so callers
     * never guess success from text (see AiTooling\ToolPipeline::normalizeResult).
     */
    public function execute(string $tool, array $resolved, $user): array
    {
        $tool = self::normalizeToolName($tool);

        $result = DB::transaction(function () use ($tool, $resolved, $user) {
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
                'plan_propose' => ['ok' => false, 'message' => __('Plans run phase by phase after structure approval.'), 'id' => null],
                // Workout plans are created via the WorkoutImport preview/confirm flow, not as a single action.
                'workout_plan_propose' => ['ok' => false, 'message' => __('Workout plans are created via the import preview after confirmation.'), 'id' => null],
                // Clarifications are never "executed": the pending row waits
                // until the user picks an option (or it expires). confirm-all
                // simply consumes the row with this message.
                'ask_user_choice' => ['ok' => true, 'message' => __('Waiting for the user to choose an option.'), 'id' => null],
                'workspace_search' => $this->execWorkspaceSearch($resolved, $user),
                default => ['ok' => false, 'message' => __('Unsupported tool'), 'id' => null],
            };
        });

        return \App\Services\AiTooling\ToolPipeline::normalizeResult($tool, $result);
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
                return $this->fail('Parent task not found or not yours.', ToolError::NOT_FOUND);
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
                return $this->fail('Project not found or not yours.', ToolError::NOT_FOUND);
            }
            $projectName = $project->name;
        } elseif (! empty($args['project'])) {
            $needle = $args['project'];
            $project = is_numeric($needle)
                ? Project::where('id', (int) $needle)->where('user_id', $user->id)->first()
                : Project::where('user_id', $user->id)->where('name', 'like', "%{$needle}%")->first();
            if (! $project) {
                return $this->fail("Project '{$needle}' not found.", ToolError::NOT_FOUND);
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
            return $this->fail($r['error'], $r['code'] ?? ToolError::VALIDATION_ERROR);
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
            return $this->fail($r['error'], $r['code'] ?? ToolError::VALIDATION_ERROR);
        }
        if ($r['task']->status === 'completed') {
            return $this->fail("Task '{$r['task']->title}' is already completed.", ToolError::CONFLICT);
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
            return $this->fail($r['error'], $r['code'] ?? ToolError::VALIDATION_ERROR);
        }

        return ['ok' => true, 'error' => null, 'resolved' => ['id' => $r['task']->id, 'title' => $r['task']->title]];
    }

    private function validateReminderComplete(array $args, $user): array
    {
        $v = Validator::make($args, ['id' => 'required|integer']);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $rem = Reminder::where('id', $args['id'])->where('user_id', $user->id)->first();
        if (! $rem) {
            return $this->fail('Reminder not found or not yours.', ToolError::NOT_FOUND);
        }
        if ((bool) $rem->is_completed) {
            return $this->fail("Reminder '{$rem->title}' is already completed.", ToolError::CONFLICT);
        }

        return ['ok' => true, 'error' => null, 'resolved' => ['id' => $rem->id, 'title' => $rem->title]];
    }

    private function validateOwned(array $args, $user, string $model, string $key = 'id'): array
    {
        $v = Validator::make($args, [$key => 'required|integer']);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $row = $model::where('id', $args[$key])->where('user_id', $user->id)->first();
        if (! $row) {
            return $this->fail('Item not found or not yours.', ToolError::NOT_FOUND);
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
            return $this->fail('Note not found or not yours.', ToolError::NOT_FOUND);
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
            return $this->fail('Note not found or not yours.', ToolError::NOT_FOUND);
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
            return $this->fail("Target {$args['target_type']} not found or not yours.", ToolError::NOT_FOUND);
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
            'daily_goal_value' => 'nullable|integer|min:1|max:10000',
            'daily_goal_unit' => 'nullable|in:tasks,pages,minutes',
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
                return $this->fail("Parent project '{$needle}' not found or not yours.", ToolError::NOT_FOUND);
            }
            if ($this->projectDepth($parent) >= 5) {
                return $this->fail('Parent project is nested too deep (max 5 levels).');
            }
            $parentId = $parent->id;
            $parentName = $parent->name;
        }

        // Duplicate guard: refuse to create a near-identical project. The
        // error carries candidates so the model reuses an existing project
        // (or asks the user which one they mean) instead of duplicating.
        if ($user !== null) {
            $matches = $this->similarProjects((int) $user->id, $args['name']);
            if ($matches->isNotEmpty()) {
                $candidates = $this->candidateList($matches);
                $names = implode(', ', array_map(fn ($c) => "'{$c['path']}' (id {$c['id']})", $candidates));

                return $this->fail(
                    "A similar project already exists: {$names}. Do NOT create a duplicate — reuse the existing project_id, or ask the user which project they mean.",
                    ToolError::DUPLICATE_TARGET
                ) + ['candidates' => $candidates];
            }
        }

        return ['ok' => true, 'error' => null, 'resolved' => [
            'name' => $args['name'],
            'description' => $args['description'] ?? null,
            'status' => $args['status'] ?? 'not_started',
            'parent_id' => $parentId,
            'parent_name' => $parentName,
            'daily_goal_value' => isset($args['daily_goal_value']) ? (int) $args['daily_goal_value'] : null,
            'daily_goal_unit' => $args['daily_goal_unit'] ?? 'tasks',
        ]];
    }

    /**
     * Normalized project-name comparison key: Arabic→Persian folding, ZWNJ,
     * punctuation and spacing stripped — mirrors AiIntentRouter::normalize
     * so server-side matching agrees with what the model sees.
     */
    private static function normProjectKey(string $name): string
    {
        $n = mb_strtolower(trim($name));
        $n = str_replace(['ي', 'ك', 'ة', 'ـ'], ['ی', 'ک', 'ه', ''], $n);
        $n = str_replace(["\u{200C}", "\u{200D}", ' '], '', $n);

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $n);
    }

    /**
     * Existing projects of $userId whose name strongly matches $name.
     * Powers the duplicate guard (project_create) and plan attach resolution
     * (plan_propose). Match tiers: exact normalized (1.0), containment of a
     * reasonably-sized key (0.9), high similarity (0.8). Short keys never
     * containment-match to avoid false positives.
     *
     * @param  array  $excludeIds  project ids to ignore
     * @param  ?int  $parentId  when set, only candidates directly under it (sub-projects)
     * @return \Illuminate\Database\Eloquent\Collection<int, Project> best first
     */
    private function similarProjects(int $userId, string $name, array $excludeIds = [], ?int $parentId = null, int $limit = 3)
    {
        $needle = self::normProjectKey($name);
        if ($needle === '') {
            return collect();
        }
        $rows = Project::where('user_id', $userId)->get(['id', 'name', 'parent_id']);
        $scored = [];
        foreach ($rows as $p) {
            if (in_array((int) $p->id, $excludeIds, true)) {
                continue;
            }
            if ($parentId !== null && (int) $p->parent_id !== $parentId) {
                continue;
            }
            $hay = self::normProjectKey((string) $p->name);
            if ($hay === '') {
                continue;
            }
            $score = 0.0;
            if ($hay === $needle) {
                $score = 1.0;
            } elseif (mb_strlen($needle) >= 4 && mb_strlen($hay) >= 4
                && (str_contains($hay, $needle) || str_contains($needle, $hay))) {
                $score = 0.9;
            } else {
                $pct = 0.0;
                similar_text($hay, $needle, $pct);
                // Near-identical only (≤1 char off in longer names):
                // anything looser misfires on "Week 1" vs "Week 2"-style
                // siblings (="chapter1" vs "chapter2" scores 87.5%).
                if ($pct >= 95.0) {
                    $score = 0.8;
                }
            }
            if ($score > 0) {
                $scored[] = [$score, $p];
            }
        }
        usort($scored, fn ($a, $b) => $b[0] <=> $a[0]);

        return collect(array_slice(array_map(fn ($s) => $s[1], $scored), 0, $limit));
    }

    /** "Parent > Child" breadcrumb (callers already user-scope the project). */
    private function projectBreadcrumb(Project $project): string
    {
        $parts = [(string) $project->name];
        $current = $project->parent_id ? Project::find($project->parent_id) : null;
        $guard = 0;
        while ($current && $guard++ < 6) {
            array_unshift($parts, (string) $current->name);
            $current = $current->parent_id ? Project::find($current->parent_id) : null;
        }

        return implode(' > ', $parts);
    }

    /** Candidate rows for duplicate/ambiguous-target errors (id, name, path). */
    private function candidateList(iterable $projects): array
    {
        $out = [];
        foreach ($projects as $p) {
            $out[] = [
                'id' => (int) $p->id,
                'name' => (string) $p->name,
                'path' => $this->projectBreadcrumb($p),
            ];
        }

        return $out;
    }

    private function validateProjectAddMember(array $args, $user): array
    {
        $v = Validator::make($args, [
            'project_id' => 'nullable|integer',
            'project' => 'nullable|string|max:255',
            // `member_id` is the server-resolved id persisted on a pending
            // action; the model still only ever sends `user`.
            'member_id' => 'nullable|integer',
            'user' => 'required_without:member_id|nullable|string|max:255',
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
                return $this->fail('Project not found or not yours.', ToolError::NOT_FOUND);
            }
        } elseif (! empty($args['project'])) {
            $needle = $args['project'];
            $project = is_numeric($needle)
                ? Project::where('id', (int) $needle)->where('user_id', $user->id)->first()
                : Project::where('user_id', $user->id)->where('name', 'like', "%{$needle}%")->first();
            if (! $project) {
                return $this->fail("Project '{$needle}' not found.", ToolError::NOT_FOUND);
            }
        } else {
            return $this->fail('Tell me which project to add the member to.');
        }

        $member = null;
        $candidates = collect();
        if (! empty($args['member_id'])) {
            // Already resolved at proposal time; re-validated fresh here.
            $member = User::find((int) $args['member_id']);
        } else {
            $resolvedMember = $this->resolveMemberUser((string) $args['user']);
            $member = $resolvedMember['user'];
            $candidates = $resolvedMember['candidates'];
        }

        if (! $member) {
            if ($candidates->isNotEmpty()) {
                $options = $this->memberCandidateList($candidates);
                $names = implode(', ', array_map(fn ($c) => "'{$c['name']}'", $options));

                return $this->fail(
                    "More than one person matches '{$args['user']}': {$names}. Ask the user which one they mean — never guess. Each option can carry their user id.",
                    ToolError::AMBIGUOUS_TARGET
                ) + ['candidates' => $options];
            }

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
     * Resolve a collaborator reference to a SINGLE unambiguous user.
     *
     * Returns ['user' => ?User, 'candidates' => Collection]. `user` is only
     * set when the reference maps to exactly one person, so a partial-name
     * match can never silently attach the wrong collaborator:
     *
     *   - numeric        → exact user id
     *   - contains '@'   → exact email
     *   - otherwise      → exact full name must match exactly one row; a
     *                     partial match is only accepted when unambiguous,
     *                     otherwise bounded candidates are returned.
     *
     * Candidates are capped (never a full user dump) and expose id + name
     * only, so the caller can turn them into an explicit user choice
     * (`ask_user_choice`) instead of an information-disclosure oracle.
     */
    private function resolveMemberUser(string $ref): array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return ['user' => null, 'candidates' => collect()];
        }

        if (is_numeric($ref)) {
            return ['user' => User::find((int) $ref), 'candidates' => collect()];
        }

        if (str_contains($ref, '@')) {
            return ['user' => User::where('email', $ref)->first(), 'candidates' => collect()];
        }

        // Exact full name: unambiguous, resolves directly.
        $exact = User::where('name', $ref)->get();
        if ($exact->count() === 1) {
            return ['user' => $exact->first(), 'candidates' => collect()];
        }
        if ($exact->count() > 1) {
            return ['user' => null, 'candidates' => $exact->take(self::MEMBER_CANDIDATE_LIMIT)];
        }

        // Partial name: only usable when it points at exactly one person.
        $partial = User::where('name', 'like', "%{$ref}%")
            ->orderBy('name')
            ->limit(self::MEMBER_CANDIDATE_LIMIT + 1)
            ->get();

        if ($partial->count() === 1) {
            return ['user' => $partial->first(), 'candidates' => collect()];
        }

        return [
            'user' => null,
            'candidates' => $partial->take(self::MEMBER_CANDIDATE_LIMIT),
        ];
    }

    /** Candidate rows for an ambiguous collaborator (id + display name only). */
    private function memberCandidateList(iterable $users): array
    {
        $out = [];
        foreach ($users as $u) {
            $out[] = [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
            ];
        }

        return $out;
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
            return $this->fail($r['error'], $r['code'] ?? ToolError::VALIDATION_ERROR);
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
            return $this->fail('Checklist item not found or not yours.', ToolError::NOT_FOUND);
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
                return ['error' => 'Task not found or not yours.', 'code' => ToolError::NOT_FOUND];
            }

            return ['task' => $task];
        }

        $needle = trim((string) ($args[$titleKey] ?? ''));
        if ($needle === '') {
            return ['error' => 'Pass the task id or its title (task).', 'code' => ToolError::VALIDATION_ERROR];
        }
        if (is_numeric($needle)) {
            $task = Task::where('id', (int) $needle)->where('user_id', $user->id)->first();
            if (! $task) {
                return ['error' => 'Task not found or not yours.', 'code' => ToolError::NOT_FOUND];
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
                return ['error' => "Project '{$pneedle}' not found.", 'code' => ToolError::NOT_FOUND];
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

            return ['error' => "Task '{$needle}' not found{$scope}.", 'code' => ToolError::NOT_FOUND];
        }
        if ($matches->count() > 1) {
            $list = $matches->take(5)->map(fn ($t) => "'{$t->title}'")->join(', ');
            $hint = $projectName ? ' Pass the task id to pick one.' : ' Pass project to disambiguate, or the task id.';

            return ['error' => "Multiple tasks match '{$needle}': {$list}.{$hint}", 'code' => ToolError::CONFLICT];
        }

        return ['task' => $matches->first()];
    }

    /**
     * Resolve a tracked routine by ID or name (owned by the user).
     *
     * Mirrors resolveTask: exact-first, bounded fuzzy, explicit CONFLICT on
     * ambiguity — never a silent first LIKE match. Miss/foreign/stale ids
     * share one NOT_FOUND message so existence is never confirmed.
     *
     * @return array{routine?: Routine}|array{error: string, code: string}
     */
    private function resolveRoutine(array $args, $user): array
    {
        if (! empty($args['routine_id'])) {
            $routine = Routine::where('id', (int) $args['routine_id'])->where('user_id', $user->id)->first();
            if (! $routine) {
                return ['error' => 'Routine not found or not yours.', 'code' => ToolError::NOT_FOUND];
            }

            return ['routine' => $routine];
        }

        $needle = trim((string) ($args['routine'] ?? ''));
        if ($needle === '') {
            return ['error' => 'Pass the routine id or its title (routine).', 'code' => ToolError::VALIDATION_ERROR];
        }
        if (is_numeric($needle)) {
            $routine = Routine::where('id', (int) $needle)->where('user_id', $user->id)->first();
            if (! $routine) {
                return ['error' => 'Routine not found or not yours.', 'code' => ToolError::NOT_FOUND];
            }

            return ['routine' => $routine];
        }

        $query = Routine::where('user_id', $user->id);
        $exact = (clone $query)->where('title', $needle)->get();
        $matches = $exact->isNotEmpty()
            ? $exact
            : (clone $query)->where('title', 'like', "%{$needle}%")->limit(6)->get();

        if ($matches->isEmpty()) {
            return ['error' => "Routine '{$needle}' not found.", 'code' => ToolError::NOT_FOUND];
        }
        if ($matches->count() > 1) {
            $list = $matches->take(5)->map(fn ($r) => "'{$r->title}'")->join(', ');

            return ['error' => "Multiple routines match '{$needle}': {$list}. Pass the routine id to pick one.", 'code' => ToolError::CONFLICT];
        }

        return ['routine' => $matches->first()];
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

        $resolution = $this->resolveRoutine($args, $user);
        if (! isset($resolution['routine'])) {
            return $this->fail($resolution['error'], $resolution['code'] ?? ToolError::NOT_FOUND);
        }
        $routine = $resolution['routine'];
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
            $item = null;
            if (is_numeric($needle)) {
                $item = $routine->checklistItems()->whereKey((int) $needle)->first();
            } else {
                $candidates = $routine->checklistItems()->where('name', $needle)->get();
                if ($candidates->isEmpty()) {
                    $candidates = $routine->checklistItems()->where('name', 'like', "%{$needle}%")->limit(6)->get();
                }
                if ($candidates->count() > 1) {
                    $list = $candidates->take(5)->map(fn ($c) => "'{$c->name}'")->join(', ');

                    return $this->fail("Multiple steps match '{$needle}': {$list}. Pass the step id (item_id) to pick one.", ToolError::CONFLICT);
                }
                $item = $candidates->first();
            }
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
            return $this->fail('Routine not found or not yours.', ToolError::NOT_FOUND);
        }
        $date = isset($args['date']) ? Carbon::parse($args['date'])->toDateString() : now()->toDateString();
        if ($routine->completedOn($date)) {
            return $this->fail("Routine '{$routine->title}' is already done for {$date}.", ToolError::CONFLICT);
        }

        return ['ok' => true, 'error' => null, 'resolved' => [
            'id' => $routine->id, 'title' => $routine->title, 'date' => $date,
        ]];
    }

    /**
     * Validate a whole build plan WITHOUT writing anything.
     * Caps: 5 root projects, 3 sub-projects each, 30 tasks, 100 subtasks,
     * 10 reminders, 10 notes. Routines stay exclusive (no mixing).
     */
    private function validatePlanPropose(array $args, $user = null): array
    {
        // Defensive: normalizeArgs() already coerces, but validateCall can be
        // reached with raw model JSON — coerce again so a `project` string
        // never surfaces as "project باید یک آرایه باشد".
        $args = $this->coercePlanArgs($args);
        $args['subprojects'] = $args['subProjects'] ?? $args['subprojects'] ?? [];
        unset($args['subProjects']);
        if (! is_array($args['subprojects'])) {
            return $this->fail('subprojects must be a list of {name, tasks[]}. Example: {"project":{"name":"Task Manager"},"subprojects":[]}.');
        }
        $args['projects'] = $args['Projects'] ?? $args['projects'] ?? [];
        unset($args['Projects']);
        if (! is_array($args['projects'])) {
            return $this->fail('projects must be a list of {name, tasks[]}. Example: {"projects":[{"name":"A","tasks":[{"title":"T1"}]}]}.');
        }
        $args['projects'] = array_values($args['projects']);
        // A single object instead of a list (or a bare string) was already
        // coerced, but guard again for hand-built calls.
        foreach (['reminders', 'notes', 'routines'] as $lk) {
            if (isset($args[$lk]) && is_array($args[$lk]) && ! empty($args[$lk]) && ! array_is_list($args[$lk])) {
                $args[$lk] = [$args[$lk]];
            }
            if (isset($args[$lk]) && is_string($args[$lk])) {
                unset($args[$lk]);
            }
        }
        $args['reminders'] = array_values((array) ($args['reminders'] ?? []));
        $args['notes'] = array_values((array) ($args['notes'] ?? []));
        $args['routines'] = array_values((array) ($args['routines'] ?? []));

        // Chunk guard rails FIRST (before the generic validator): an
        // oversized study/book build must fail with guidance toward the
        // next chunk — not with a cryptic "max N items" message that sends
        // the user into a retry loop.
        $chunkHint = ' Propose ONE chunk instead: the book project + at most 3 chapters, lessons as tasks, micro-topics as subtasks — then continue chunk by chunk (e.g. "بسته اول: فصل‌های ۱ تا ۳").';
        if (is_array($args['subprojects'] ?? null) && count($args['subprojects']) > \App\Models\AiPlan::MAX_SUBPROJECTS) {
            return $this->fail('Too many sub-projects in one plan (max ' . \App\Models\AiPlan::MAX_SUBPROJECTS . ', got ' . count($args['subprojects']) . ').' . $chunkHint);
        }
        if (is_array($args['projects'] ?? null) && count($args['projects']) > \App\Models\AiPlan::MAX_PROJECTS) {
            return $this->fail('Too many projects in one plan (max ' . \App\Models\AiPlan::MAX_PROJECTS . ', got ' . count($args['projects']) . ').' . $chunkHint);
        }
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
            'project.daily_goal_value' => 'nullable|integer|min:1|max:10000',
            'project.daily_goal_unit' => 'nullable|in:tasks,pages,minutes',
            'projects.*.daily_goal_value' => 'nullable|integer|min:1|max:10000',
            'projects.*.daily_goal_unit' => 'nullable|in:tasks,pages,minutes',
            'subprojects.*.daily_goal_value' => 'nullable|integer|min:1|max:10000',
            'subprojects.*.daily_goal_unit' => 'nullable|in:tasks,pages,minutes',
            'project.project_id' => 'nullable|integer',
            'projects.*.project_id' => 'nullable|integer',
            'subprojects.*.project_id' => 'nullable|integer',
            'reminders' => 'nullable|array|max:' . \App\Models\AiPlan::MAX_REMINDERS,
            'notes' => 'nullable|array|max:' . \App\Models\AiPlan::MAX_NOTES,
            'routines' => 'nullable|array|max:' . self::MAX_PLAN_ROUTINES,
        ]);
        if ($v->fails()) {
            return $this->fail($this->friendlyPlanError($v));
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
            $tasks = $this->coerceTaskList($tasks);
            $out = [];
            foreach ($tasks as $t) {
                if (! is_array($t)) {
                    return $this->fail("A task in {$where} is malformed. Each task must be {title}.");
                }
                $t['subtasks'] = $t['subTasks'] ?? $t['subtasks'] ?? [];
                unset($t['subTasks']);
                $t['subtasks'] = $this->coerceTaskList($t['subtasks']);
                // Verbatim user text: keep the FULL text — if the title is over
                // 120 chars, shorten the title and preserve everything in
                // description (max 500, truncated with care for multibyte).
                $rawTitle = trim((string) ($t['title'] ?? ''));
                $rawDesc = isset($t['description']) ? trim((string) $t['description']) : null;
                if ($rawTitle !== '' && mb_strlen($rawTitle) > 120) {
                    $short = mb_substr($rawTitle, 0, 117).'…';
                    $full = $rawTitle.($rawDesc ? "\n\n".$rawDesc : '');
                    $t['title'] = $short;
                    $t['description'] = mb_substr($full, 0, 500);
                }
                if (isset($t['description']) && is_string($t['description']) && mb_strlen($t['description']) > 500) {
                    $t['description'] = mb_substr($t['description'], 0, 500);
                }
                $tv = Validator::make($t, [
                    'title' => 'required|string|max:120',
                    'due_date' => 'nullable|date',
                    'priority' => 'nullable|in:low,medium,high',
                    'description' => 'nullable|string|max:500',
                    'weight' => 'nullable|numeric|min:0|max:99',
                    'subtasks' => 'nullable|array',
                    'subtasks.*.title' => 'required|string|max:120',
                    'subtasks.*.weight' => 'nullable|numeric|min:0|max:99',
                ]);
                if ($tv->fails()) {
                    return $this->fail("{$where}: ".$tv->errors()->first());
                }
                $subs = [];
                foreach ((array) ($t['subtasks'] ?? []) as $s) {
                    if (is_string($s) || is_numeric($s)) {
                        $s = ['title' => (string) $s];
                    }
                    $st = trim((string) ($s['title'] ?? ''));
                    if ($st === '') {
                        continue;
                    }
                    $sw = $s['weight'] ?? null;
                    $subs[] = [
                        'title' => mb_substr($st, 0, 120),
                        'weight' => is_numeric($sw) ? max(0, min(99, (float) $sw)) : null,
                    ];
                }
                $out[] = [
                    'title' => trim((string) $t['title']),
                    'due_date' => $t['due_date'] ?? null,
                    'priority' => $t['priority'] ?? 'medium',
                    'description' => $t['description'] ?? null,
                    'weight' => isset($t['weight']) && is_numeric($t['weight']) ? max(0, min(99, (float) $t['weight'])) : null,
                    'subtasks' => $subs,
                ];
            }

            return $out;
        };

        // Normalize every root project to {name, description, tasks[], subprojects[], members[]}.
        // Empty daily_goal_value disables the goal (both columns nulled).
        $cleanGoal = function ($node) {
            $value = $node['daily_goal_value'] ?? null;
            if ($value === null || $value === '') {
                return ['daily_goal_value' => null, 'daily_goal_unit' => null];
            }
            $unit = $node['daily_goal_unit'] ?? 'tasks';

            return [
                'daily_goal_value' => max(1, min(10000, (int) $value)),
                'daily_goal_unit' => in_array($unit, Project::DAILY_GOAL_UNITS, true) ? $unit : 'tasks',
            ];
        };
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
                    'project_id' => isset($p['project_id']) && $p['project_id'] !== null ? (int) $p['project_id'] : null,
                    'tasks' => $tasks,
                    'subprojects' => [],
                    'members' => $cleanMembers($p['members'] ?? []),
                ] + $cleanGoal($p);
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
                        'project_id' => isset($sub['project_id']) && $sub['project_id'] !== null ? (int) $sub['project_id'] : null,
                        'tasks' => $tasks,
                    ] + $cleanGoal($sub);
                }
                $roots[] = [
                    'name' => trim((string) $args['project']['name']),
                    'description' => $args['project']['description'] ?? null,
                    'project_id' => isset($args['project']['project_id']) && $args['project']['project_id'] !== null ? (int) $args['project']['project_id'] : null,
                    'tasks' => $direct,
                    'subprojects' => $subs,
                    'members' => $cleanMembers($args['project']['members'] ?? []),
                ] + $cleanGoal($args['project']);
            }
        }

        // Duplicate guard + explicit attach: reuse an existing project when
        // (a) the model passed project_id (from the PROJECTS context tree),
        // (b) or the node's name strongly matches an existing project.
        // Attached nodes execute against the existing record — the preview
        // shows the reuse, so the user still confirms before anything runs.
        if ($user !== null && ! empty($roots)) {
            $roots = $this->resolvePlanAttachments($roots, (int) $user->id);
            if (isset($roots['ok'])) {
                return $roots;
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
        // NOTE: a project tree with zero tasks is NOT rejected here —
        // bare skeletons and member-only plans are legitimate flows. The
        // safety net for accidentally-empty builds is the UI: the proposed
        // card shows an empty-content warning strip and the done card
        // renders real created counts (never a static success claim).
        if ($taskCount > \App\Models\AiPlan::MAX_TASKS) {
            return $this->fail('Too many tasks in one plan (max ' . \App\Models\AiPlan::MAX_TASKS . ', got ' . $taskCount . '). Split into smaller chunks — e.g. one plan per 2-3 chapters — and attach later chunks via project_id.');
        }
        if ($subCount > \App\Models\AiPlan::MAX_SUBTASKS) {
            return $this->fail('Too many subtasks in one plan (max ' . \App\Models\AiPlan::MAX_SUBTASKS . ', got ' . $subCount . '). Split into smaller chunks — e.g. one plan per 2-3 chapters — and attach later chunks via project_id.');
        }

        // Legacy keys stay populated so old readers keep working; the
        // unified `projects` key is the source of truth for new code.
        $first = $roots[0] ?? null;
        $structure = [
            'projects' => $roots,
            'project' => $first ? [
                'name' => $first['name'],
                'description' => $first['description'],
                'project_id' => $first['project_id'] ?? null,
                'attached' => $first['attached'] ?? false,
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
     * Resolve plan project/sub-project nodes to EXISTING records where
     * possible: explicit project_id wins; otherwise a strong name match
     * auto-attaches (the duplicate guard's counterpart for plans).
     *
     * Sub-projects only auto-attach to a candidate under the SAME resolved
     * parent — an equal name in another branch is a different sub-project,
     * not a duplicate. New roots skip sub attach (parent id doesn't exist yet).
     *
     * @return array roots (mutated) | failure array with 'ok' => false
     */
    private function resolvePlanAttachments(array $roots, int $userId)
    {
        $check = function ($id, string $label) use ($userId) {
            $existing = Project::where('id', (int) $id)->where('user_id', $userId)->first();

            return $existing ?: $this->fail("{$label} #{$id} not found or not yours.", ToolError::NOT_FOUND);
        };

        foreach ($roots as $i => $root) {
            $explicit = $root['project_id'] ?? null;
            if ($explicit !== null) {
                $existing = $check($explicit, 'Project');
                if (isset($existing['ok'])) {
                    return $existing;
                }
                $roots[$i]['project_id'] = (int) $existing->id;
                $roots[$i]['attached'] = true;
                $roots[$i]['attached_name'] = (string) $existing->name;
            } else {
                // Attach ONLY on an exact (normalized) name match. A merely
                // similar project must never be silently picked: the node
                // falls through to create-new, where the DUPLICATE_TARGET
                // guard still catches true near-duplicates at execution.
                $match = $this->similarProjects($userId, $root['name']);
                if ($match->isNotEmpty()
                    && self::normProjectKey((string) $match[0]->name) === self::normProjectKey((string) $root['name'])) {
                    $roots[$i]['project_id'] = (int) $match[0]->id;
                    $roots[$i]['attached'] = true;
                    $roots[$i]['attached_name'] = (string) $match[0]->name;
                }
            }

            $parentId = $roots[$i]['project_id'] ?? null;
            foreach (array_values($root['subprojects'] ?? []) as $si => $sub) {
                $sExplicit = $sub['project_id'] ?? null;
                if ($sExplicit !== null) {
                    $existing = $check($sExplicit, 'Sub-project');
                    if (isset($existing['ok'])) {
                        return $existing;
                    }
                    $roots[$i]['subprojects'][$si]['project_id'] = (int) $existing->id;
                    $roots[$i]['subprojects'][$si]['attached'] = true;
                } elseif ($parentId !== null) {
                    $match = $this->similarProjects($userId, $sub['name'], [(int) $parentId], (int) $parentId, 1);
                    if ($match->isNotEmpty()
                        && self::normProjectKey((string) $match[0]->name) === self::normProjectKey((string) $sub['name'])) {
                        $roots[$i]['subprojects'][$si]['project_id'] = (int) $match[0]->id;
                        $roots[$i]['subprojects'][$si]['attached'] = true;
                    }
                }
            }
        }

        return $roots;
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
    /* ── workspace_search ───────────────────────────────────── */

    private function validateWorkspaceSearch(array $args): array
    {
        $v = Validator::make($args, [
            'query' => 'required|string|max:255',
            'kinds' => 'nullable|array|max:5',
            'kinds.*' => 'string|in:project,task,note,routine,reminder',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $kinds = array_values(array_intersect(
            array_map('strval', (array) ($args['kinds'] ?? [])),
            ['project', 'task', 'note', 'routine', 'reminder']
        ));

        return ['ok' => true, 'error' => null, 'resolved' => [
            'query' => trim((string) $args['query']),
            'kinds' => $kinds,
            'kinds_label' => empty($kinds) ? 'all' : implode(',', $kinds),
            'limit' => max(1, min(20, (int) ($args['limit'] ?? 8))),
        ]];
    }

    /**
     * Persian-friendly search across the user's own workspace.
     *
     * Matching runs in PHP on normalized text (AiIntentRouter::normalize:
     * Arabic→Persian folding, ZWNJ/punctuation stripped) so «كتابخانه» finds
     * «کتاب‌خانه». Data is bounded per kind (400 newest rows) — a personal
     * workspace fits comfortably and the result set is small by design.
     */
    private function execWorkspaceSearch(array $r, $user): array
    {
        $query = trim((string) ($r['query'] ?? ''));
        $needle = \App\Services\AiIntentRouter::normalize($query);
        if ($needle === '') {
            return ['ok' => false, 'message' => __('Give a non-empty search query.')];
        }
        $tokens = array_values(array_filter(explode(' ', $needle), fn ($w) => mb_strlen($w) >= 2));
        $kinds = array_values(array_intersect((array) ($r['kinds'] ?? []), ['project', 'task', 'note', 'routine', 'reminder']))
            ?: ['project', 'task', 'note', 'routine', 'reminder'];
        $limit = max(1, min(20, (int) ($r['limit'] ?? 8)));
        $uid = $user->id;

        $candidates = [];
        if (in_array('project', $kinds, true)) {
            foreach (Project::where('user_id', $uid)->orderByDesc('id')->limit(400)->get(['id', 'name', 'parent_id']) as $p) {
                $candidates[] = ['kind' => 'project', 'id' => $p->id, 'title' => (string) $p->name, 'extra' => $this->projectBreadcrumb($p)];
            }
        }
        if (in_array('task', $kinds, true)) {
            foreach (Task::where('user_id', $uid)->with('project:id,name')->orderByDesc('id')->limit(400)->get(['id', 'title', 'project_id', 'due_date']) as $t) {
                $extra = [];
                if ($t->project?->name) {
                    $extra[] = 'project: '.$t->project->name;
                }
                if ($t->due_date) {
                    $extra[] = 'due: '.($t->due_date instanceof \DateTimeInterface ? $t->due_date->format('Y-m-d') : (string) $t->due_date);
                }
                $candidates[] = ['kind' => 'task', 'id' => $t->id, 'title' => (string) $t->title, 'extra' => implode(', ', $extra)];
            }
        }
        if (in_array('note', $kinds, true)) {
            foreach (Note::where('user_id', $uid)->orderByDesc('id')->limit(200)->get(['id', 'title']) as $n) {
                $candidates[] = ['kind' => 'note', 'id' => $n->id, 'title' => (string) $n->title, 'extra' => ''];
            }
        }
        if (in_array('routine', $kinds, true)) {
            foreach (Routine::where('user_id', $uid)->orderByDesc('id')->limit(200)->get(['id', 'title']) as $rt) {
                $candidates[] = ['kind' => 'routine', 'id' => $rt->id, 'title' => (string) $rt->title, 'extra' => ''];
            }
        }
        if (in_array('reminder', $kinds, true)) {
            foreach (Reminder::where('user_id', $uid)->orderByDesc('id')->limit(200)->get(['id', 'title']) as $rm) {
                $candidates[] = ['kind' => 'reminder', 'id' => $rm->id, 'title' => (string) $rm->title, 'extra' => ''];
            }
        }

        $scored = [];
        foreach ($candidates as $c) {
            $hay = \App\Services\AiIntentRouter::normalize($c['title']);
            if ($hay === '') {
                continue;
            }
            $score = 0;
            if (str_contains($hay, $needle)) {
                $score += 5;
            }
            foreach ($tokens as $w) {
                if (str_contains($hay, $w)) {
                    $score += 1;
                }
            }
            // Threshold: an exact-phrase hit scores 5+, so this only drops
            // single-shared-token noise (e.g. query «روانشناسی رشد»
            // matching «روانشناسی شناختی» on «روانشناسی» alone) that
            // previously surfaced as a confident "✅ match".
            if ($score >= 2) {
                $c['score'] = $score;
                $scored[] = $c;
            }
        }
        usort($scored, fn ($a, $b) => [$b['score'], $b['id']] <=> [$a['score'], $a['id']]);
        $top = array_slice($scored, 0, $limit);

        if (empty($top)) {
            return ['ok' => true, 'message' => __('No matches for ":query". The item may not exist — ask the user instead of creating anything.', ['query' => $query])];
        }
        $lines = [];
        foreach ($top as $c) {
            $lines[] = '- ['.$c['kind'].' #'.$c['id'].'] '.$c['title'].($c['extra'] !== '' ? ' ('.$c['extra'].')' : '');
        }
        $total = count($scored);

        return ['ok' => true, 'message' => __(':n match(es) for ":query":', ['n' => $total, 'query' => $query])."\n".implode("\n", $lines)];
    }

    /* ── ask_user_choice ────────────────────────────────────── */

    private function validateAskUserChoice(array $args): array
    {
        $v = Validator::make($args, [
            'question' => 'required|string|max:500',
            'options' => 'required|array|min:2|max:5',
            'options.*.label' => 'required|string|max:120',
            'options.*.tool' => 'required|string|max:60',
            'options.*.args' => 'nullable|array',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $clean = [];
        foreach (array_values($args['options']) as $i => $o) {
            $label = trim((string) ($o['label'] ?? ''));
            $tool = self::normalizeToolName((string) ($o['tool'] ?? ''));
            if ($label === '') {
                return $this->fail('Every option needs a label.');
            }
            // The chosen option is proposed (and re-validated) as a normal
            // tool call later — only structural sanity is checked here.
            if ($tool === 'ask_user_choice' || ! in_array($tool, self::TOOLS, true)) {
                return $this->fail("Option ".($i + 1)." has an unknown tool '{$tool}'.");
            }
            $clean[] = ['label' => $label, 'tool' => $tool, 'args' => (array) ($o['args'] ?? [])];
        }

        return ['ok' => true, 'error' => null, 'resolved' => [
            'question' => trim((string) $args['question']),
            'options' => $clean,
        ]];
    }

    public function planRoots(array $structure): array
    {
        if (! empty($structure['projects'])) {
            return array_values($structure['projects']);
        }
        if (! empty($structure['project']['name'])) {
            return [[
                'name' => $structure['project']['name'],
                'description' => $structure['project']['description'] ?? null,
                'project_id' => $structure['project']['project_id'] ?? null,
                'attached' => $structure['project']['attached'] ?? false,
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
            // Stale-plan guard: a cancelled/expired plan (or one owned by
            // someone else) must never execute here, and must never be
            // resurrected by the terminal write below. The HTTP endpoints
            // check this too, but this service is also called directly.
            $fresh = $plan->fresh();
            if (! $fresh || (int) $fresh->user_id !== (int) $user->id) {
                return ['ok' => false, 'message' => __('Plan not found.'), 'phase' => null];
            }
            if (! $fresh->isActionable()) {
                return ['ok' => false, 'message' => __('This plan is no longer active. Ask Lina to propose it again.'), 'phase' => null];
            }
            $plan = $fresh;

            $idx = $plan->current_phase;
            $phases = $plan->phases;
            $phase = $phases[$idx] ?? null;
            if (! $phase || ($phase['status'] ?? null) === 'done') {
                return ['ok' => false, 'message' => __('No pending phase.'), 'phase' => $phase];
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
                $message = __(':count routine(s) created.', ['count' => count($ids)]);
            } elseif ($key === 'project' || $key === 'projects') {
                // Legacy stored plans have a single root via project/subprojects.
                $created = [];
                $attachedNames = [];
                $membersAdded = 0;
                $membersSkipped = [];
                foreach ($roots as $root) {
                    if (! empty($root['project_id'])) {
                        // Attach node: reuse the existing project (re-checked
                        // at execution time — state may have changed since).
                        $existing = Project::where('id', (int) $root['project_id'])
                            ->where('user_id', $user->id)->first();
                        if (! $existing) {
                            return ['ok' => false, 'message' => __('Plan project is missing. Cancel and start over.'), 'phase' => $phase];
                        }
                        $project = $existing;
                        $attachedNames[] = (string) $project->name;
                    } else {
                        $project = $user->projects()->create([
                            'name' => $root['name'],
                            'description' => $root['description'] ?? null,
                            'status' => 'in_progress',
                            'type' => 'project',
                            'sort_order' => 0,
                            'daily_goal_value' => $root['daily_goal_value'] ?? null,
                            'daily_goal_unit' => ! empty($root['daily_goal_value']) ? ($root['daily_goal_unit'] ?? 'tasks') : null,
                        ]);
                    }
                    $created[] = [
                        'id' => $project->id,
                        'name' => $project->name,
                        'attached' => ! empty($root['project_id']),
                    ];
                    foreach (($root['members'] ?? []) as $ref) {
                        ['user' => $member, 'candidates' => $memberCandidates] =
                            $this->resolveMemberUser((string) $ref);
                        if ($member && (int) $member->id !== (int) $user->id) {
                            $project->users()->syncWithoutDetaching([$member->id => ['role' => 'member']]);
                            $membersAdded++;
                        } else {
                            // Never guess a collaborator inside a plan build:
                            // ambiguity and absence are both reported, skipped.
                            $membersSkipped[] = $memberCandidates->isNotEmpty()
                                ? $ref.' (ambiguous)'
                                : $ref;
                        }
                    }
                }
                if (empty($created)) {
                    return ['ok' => false, 'message' => __('Plan has no projects. Cancel and start over.'), 'phase' => $phase];
                }
                $phase['result'] = [
                    'projects' => $created,
                    // Legacy compat for old readers.
                    'project_id' => $created[0]['id'],
                    'project_ids' => array_column($created, 'id'),
                    'project_name' => $created[0]['name'],
                ];
                $message = count($created) === 1
                    ? __("Project ':name' created.", ['name' => $created[0]['name']])
                    : __(':count projects created.', ['count' => count($created)]);
                if ($membersAdded > 0) {
                    $message .= __(' :count member(s) added.', ['count' => $membersAdded]);
                }
                if (! empty($attachedNames)) {
                    $message .= __(' Attached to existing project(s): :names.', ['names' => implode(', ', $attachedNames)]);
                }
                if (! empty($membersSkipped)) {
                    $message .= __(' Skipped unknown users: ') . implode(', ', $membersSkipped) . '.';
                }
            } elseif ($key === 'subprojects') {
                $projectEntries = $phases[0]['result']['projects'] ?? null;
                if (! $projectEntries && isset($phases[0]['result']['project_id'])) {
                    $projectEntries = [['id' => $phases[0]['result']['project_id'], 'name' => $phases[0]['result']['project_name'] ?? '']];
                }
                if (! $projectEntries) {
                    return ['ok' => false, 'message' => __('Plan project is missing. Cancel and start over.'), 'phase' => $phase];
                }
                $subsByRoot = [];
                $flatIds = [];
                $createdFresh = [];
                foreach ($roots as $pi => $root) {
                    $parentId = $projectEntries[$pi]['id'] ?? $projectEntries[0]['id'];
                    $parent = Project::where('id', $parentId)->where('user_id', $user->id)->first();
                    if (! $parent) {
                        return ['ok' => false, 'message' => __('Plan project is missing. Cancel and start over.'), 'phase' => $phase];
                    }
                    foreach (($root['subprojects'] ?? []) as $sub) {
                        if (! empty($sub['project_id'])) {
                            $existingSub = Project::where('id', (int) $sub['project_id'])
                                ->where('user_id', $user->id)
                                ->where('parent_id', $parent->id)
                                ->first();
                            if ($existingSub) {
                                $subsByRoot[$pi][] = $existingSub->id;
                                $flatIds[] = $existingSub->id;
                                continue;
                            }
                            // Parent changed since validation → fall through
                            // and create the sub-project fresh.
                        }
                        $created = $user->projects()->create([
                            'name' => $sub['name'],
                            'description' => $sub['description'] ?? null,
                            'status' => 'in_progress',
                            'parent_id' => $parent->id,
                            'type' => 'project',
                            'sort_order' => 0,
                            'daily_goal_value' => $sub['daily_goal_value'] ?? null,
                            'daily_goal_unit' => ! empty($sub['daily_goal_value']) ? ($sub['daily_goal_unit'] ?? 'tasks') : null,
                        ]);
                        $subsByRoot[$pi][] = $created->id;
                        $flatIds[] = $created->id;
                        // Rollback only removes what the plan created — never
                        // records it attached to (see created_items).
                        $createdFresh[] = $created->id;
                    }
                }
                $phase['result'] = ['subs' => $subsByRoot, 'sub_ids' => $flatIds, 'created_sub_ids' => $createdFresh];
                $message = __(':count sub-project(s) created.', ['count' => count($flatIds)]);
            } elseif ($key === 'tasks') {
                $projectEntries = $phases[0]['result']['projects'] ?? null;
                if (! $projectEntries && isset($phases[0]['result']['project_id'])) {
                    $projectEntries = [['id' => $phases[0]['result']['project_id'], 'name' => '']];
                }
                if (! $projectEntries) {
                    return ['ok' => false, 'message' => __('Plan project is missing. Cancel and start over.'), 'phase' => $phase];
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
                $message = __(':count task(s) created.', ['count' => $phase['total']]);
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
                    return ['ok' => false, 'message' => __('Plan tasks are missing. Cancel and start over.'), 'phase' => $phase];
                }
                $n = 0;
                $subTaskIds = [];
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
                            $subTaskIds[] = $this->createPlanTask($user, $projectId, $directIds[$ti], ['title' => $s['title'], 'weight' => $s['weight'] ?? null]);
                            $n++;
                        }
                    }
                    foreach (($structure['subprojects'] ?? []) as $si => $sub) {
                        foreach (($sub['tasks'] ?? []) as $ti => $t) {
                            foreach (($t['subtasks'] ?? []) as $s) {
                                $subTaskIds[] = $this->createPlanTask($user, $subIds[$si] ?? $projectId, $subsIds[$si][$ti], ['title' => $s['title'], 'weight' => $s['weight'] ?? null]);
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
                                $subTaskIds[] = $this->createPlanTask($user, $projectId, $parentTaskId, ['title' => $s['title'], 'weight' => $s['weight'] ?? null]);
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
                                    $subTaskIds[] = $this->createPlanTask($user, $subProjectId, $parentTaskId, ['title' => $s['title'], 'weight' => $s['weight'] ?? null]);
                                    $n++;
                                }
                            }
                        }
                    }
                }
                $phase['result'] = ['created' => $n, 'subtask_ids' => $subTaskIds];
                $message = __(':count subtask(s) added.', ['count' => $n]);
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
                $message = __(':count reminder(s) created.', ['count' => count($ids)]);
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
                $message = __(':count note(s) created.', ['count' => count($ids)]);
            } else {
                return ['ok' => false, 'message' => __('Unknown plan phase.'), 'phase' => $phase];
            }

            $phase['done'] = $phase['total'];
            $phase['status'] = 'done';
            $phases[$idx] = $phase;

            // Rollback ledger: remember exactly what THIS phase created
            // (attached pre-existing records are never recorded).
            $plan->created_items = $this->mergeCreatedItems(
                $plan->created_items ?? [],
                $this->createdItemsFromPhase($key, $phase)
            );

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
                $message .= __(' Plan complete ✅');
            }
            $plan->touchExpiry();
            $plan->save();

            return ['ok' => true, 'message' => $message, 'phase' => $phase];
        });
    }

    /**
     * Extract plan-created record ids from a finished phase result.
     * Attached (pre-existing) records are deliberately excluded — rollback
     * must only remove what the plan itself created.
     *
     * @return array{projects: array<int>, tasks: array<int>, reminders: array<int>, notes: array<int>, routines: array<int>}
     */
    public function createdItemsFromPhase(string $key, array $phase): array
    {
        $empty = ['projects' => [], 'tasks' => [], 'reminders' => [], 'notes' => [], 'routines' => []];
        $result = $phase['result'] ?? [];

        $flatten = function ($v) use (&$flatten): array {
            if (! is_array($v)) {
                return is_numeric($v) ? [(int) $v] : [];
            }
            $out = [];
            foreach ($v as $item) {
                $out = array_merge($out, $flatten($item));
            }

            return $out;
        };

        switch ($key) {
            case 'routines':
                $empty['routines'] = $flatten($result['routine_ids'] ?? []);
                break;
            case 'project':
            case 'projects':
                foreach ((array) ($result['projects'] ?? []) as $entry) {
                    if (is_array($entry) && empty($entry['attached']) && isset($entry['id'])) {
                        $empty['projects'][] = (int) $entry['id'];
                    }
                }
                break;
            case 'subprojects':
                $empty['projects'] = $flatten($result['created_sub_ids'] ?? []);
                break;
            case 'tasks':
                $ids = $result['task_ids'] ?? [];
                $empty['tasks'] = array_merge(
                    $flatten($ids['direct'] ?? []),
                    $flatten($ids['subs'] ?? [])
                );
                break;
            case 'subtasks':
                $empty['tasks'] = $flatten($result['subtask_ids'] ?? []);
                break;
            case 'reminders':
                $empty['reminders'] = $flatten($result['reminder_ids'] ?? []);
                break;
            case 'notes':
                $empty['notes'] = $flatten($result['note_ids'] ?? []);
                break;
        }

        foreach ($empty as $kind => $ids) {
            $empty[$kind] = array_values(array_unique(array_map('intval', $ids)));
        }

        return $empty;
    }

    public function mergeCreatedItems(array $current, array $added): array
    {
        $base = ['projects' => [], 'tasks' => [], 'reminders' => [], 'notes' => [], 'routines' => []];
        foreach (array_keys($base) as $kind) {
            $base[$kind] = array_values(array_unique(array_merge(
                array_map('intval', (array) ($current[$kind] ?? [])),
                array_map('intval', (array) ($added[$kind] ?? []))
            )));
        }

        return $base;
    }

    /**
     * Preview what a rollback would delete / keep, without touching
     * anything. Counts are computed from the same ledger + same keep-rules
     * as rollbackPlan, so the preview the user confirms is exactly what
     * the confirmed rollback may do. Never throws for missing records.
     */
    public function rollbackPreview(\App\Models\AiPlan $plan, $user): array
    {
        $fresh = $plan->fresh();
        if (! $fresh || (int) $fresh->user_id !== (int) $user->id) {
            return ['ok' => false, 'code' => 'not_found', 'error' => __('Plan not found.')];
        }
        $items = $fresh->created_items ?? [];
        $taskIds = array_values(array_unique(array_map('intval', (array) ($items['tasks'] ?? []))));
        $projectIds = array_values(array_unique(array_map('intval', (array) ($items['projects'] ?? []))));
        $reminderIds = array_values(array_unique(array_map('intval', (array) ($items['reminders'] ?? []))));
        $noteIds = array_values(array_unique(array_map('intval', (array) ($items['notes'] ?? []))));
        $routineIds = array_values(array_unique(array_map('intval', (array) ($items['routines'] ?? []))));

        $existingTasks = $taskIds === [] ? collect() : Task::where('user_id', $user->id)->whereIn('id', $taskIds)->get(['id', 'title']);
        $existingReminders = $reminderIds === [] ? 0 : Reminder::where('user_id', $user->id)->whereIn('id', $reminderIds)->count();
        $existingNotes = $noteIds === [] ? 0 : Note::where('user_id', $user->id)->whereIn('id', $noteIds)->count();
        $existingRoutines = $routineIds === [] ? collect() : Routine::where('user_id', $user->id)->whereIn('id', $routineIds)->get(['id', 'title']);
        $existingProjects = $projectIds === [] ? collect() : Project::where('user_id', $user->id)->whereIn('id', $projectIds)->get(['id', 'name']);

        $wouldDelete = [
            'tasks' => $existingTasks->count(),
            'reminders' => $existingReminders,
            'notes' => $existingNotes,
            'routines' => $existingRoutines->count(),
            'projects' => 0,
        ];
        $kept = [];
        if ($existingProjects->isNotEmpty()) {
            foreach ($existingProjects as $project) {
                // Tasks in this project NOT in the ledger = user's later work.
                $remaining = Task::where('user_id', $user->id)->where('project_id', $project->id)->whereNotIn('id', $taskIds)->count();
                if ($remaining > 0) {
                    $kept[] = ['id' => $project->id, 'name' => $project->name, 'reason' => 'holds_user_tasks', 'count' => $remaining];
                } else {
                    $wouldDelete['projects']++;
                }
            }
        }

        return [
            'ok' => true,
            'items' => [
                ['kind' => 'tasks', 'ids' => $existingTasks->pluck('id')->all(), 'titles' => $existingTasks->pluck('title')->take(10)->all()],
                ['kind' => 'reminders', 'ids' => [], 'count' => $existingReminders],
                ['kind' => 'notes', 'ids' => [], 'count' => $existingNotes],
                ['kind' => 'routines', 'ids' => $existingRoutines->pluck('id')->all(), 'titles' => $existingRoutines->pluck('title')->take(10)->all()],
                ['kind' => 'projects', 'ids' => $existingProjects->pluck('id')->all(), 'names' => $existingProjects->pluck('name')->take(10)->all()],
            ],
            'would_delete' => $wouldDelete,
            'kept' => $kept,
            // Binds this preview to the exact ledger snapshot: if anything
            // changes before confirm, the token no longer verifies.
            'preview_token' => self::rollbackPreviewToken($fresh),
        ];
    }

    /**
     * Stateless preview token: HMAC over (plan, canonical ledger) in a
     * 5-minute window. No DB row, nothing to clean up; a changed ledger
     * (or a forged token) simply fails verification.
     */
    public static function rollbackPreviewToken(\App\Models\AiPlan $plan): string
    {
        $ledger = self::canonicalLedger($plan->created_items ?? []);
        $window = (int) floor(time() / 300);

        return hash_hmac('sha256', $plan->id.'|'.$ledger.'|'.$window, (string) config('app.key'));
    }

    public static function verifyRollbackPreviewToken(\App\Models\AiPlan $plan, string $token): bool
    {
        $ledger = self::canonicalLedger($plan->created_items ?? []);
        $key = (string) config('app.key');
        foreach ([(int) floor(time() / 300), (int) floor(time() / 300) - 1] as $window) {
            $expected = hash_hmac('sha256', $plan->id.'|'.$ledger.'|'.$window, $key);
            if (hash_equals($expected, $token)) {
                return true;
            }
        }

        return false;
    }

    private static function canonicalLedger($createdItems): string
    {
        $canon = [];
        foreach (['projects', 'tasks', 'reminders', 'notes', 'routines'] as $kind) {
            $ids = array_map('intval', (array) ($createdItems[$kind] ?? []));
            sort($ids);
            $canon[$kind] = array_values(array_unique($ids));
        }

        return md5(json_encode($canon));
    }

    /**
     * Delete everything a plan created (reverse dependency order), owned
     * records only. Projects that gained non-plan content afterwards are
     * KEPT and reported — rollback never destroys the user's later work.
     * Attached (pre-existing) records are never touched.
     */
    public function rollbackPlan(\App\Models\AiPlan $plan, $user): array
    {
        return DB::transaction(function () use ($plan, $user) {
            $fresh = $plan->fresh();
            if (! $fresh || (int) $fresh->user_id !== (int) $user->id) {
                return ['ok' => false, 'message' => __('Plan not found.')];
            }

            $items = $fresh->created_items ?? [];
            $taskIds = array_map('intval', (array) ($items['tasks'] ?? []));
            $projectIds = array_map('intval', (array) ($items['projects'] ?? []));
            $reminderIds = array_map('intval', (array) ($items['reminders'] ?? []));
            $noteIds = array_map('intval', (array) ($items['notes'] ?? []));
            $routineIds = array_map('intval', (array) ($items['routines'] ?? []));

            if ($taskIds === [] && $projectIds === [] && $reminderIds === [] && $noteIds === [] && $routineIds === []) {
                return ['ok' => true, 'message' => __('Nothing left to delete — the plan created no surviving items.'), 'deleted' => [], 'kept' => []];
            }

            $deleted = ['tasks' => 0, 'projects' => 0, 'reminders' => 0, 'notes' => 0, 'routines' => 0];
            $kept = [];

            // 1) Tasks first (subtasks cascade via FK).
            if ($taskIds !== []) {
                $tasks = Task::where('user_id', $user->id)->whereIn('id', $taskIds)->get(['id']);
                foreach ($tasks as $task) {
                    $task->delete();
                    $deleted['tasks']++;
                }
            }

            // 2) Reminders, notes (links/revisions cascade), routines (soft).
            if ($reminderIds !== []) {
                $deleted['reminders'] = Reminder::where('user_id', $user->id)->whereIn('id', $reminderIds)->delete();
            }
            if ($noteIds !== []) {
                $deleted['notes'] = Note::where('user_id', $user->id)->whereIn('id', $noteIds)->delete();
            }
            if ($routineIds !== []) {
                $routines = Routine::where('user_id', $user->id)->whereIn('id', $routineIds)->get(['id']);
                foreach ($routines as $routine) {
                    $routine->delete();
                    $deleted['routines']++;
                }
            }

            // 3) Projects last, children before parents. A project that still
            // holds tasks the plan did NOT create (user's later work) is kept.
            if ($projectIds !== []) {
                $projects = Project::where('user_id', $user->id)->whereIn('id', $projectIds)->get(['id', 'name', 'parent_id']);
                $byId = $projects->keyBy('id');
                $depthOf = function ($id) use ($byId): int {
                    $depth = 0;
                    $guard = 0;
                    $parent = $byId->get($id)?->parent_id;
                    while ($parent !== null && $byId->has($parent) && $guard++ < 50) {
                        $depth++;
                        $parent = $byId->get($parent)->parent_id;
                    }

                    return $depth;
                };
                $ordered = $projects->sortByDesc(fn ($p) => $depthOf($p->id))->values();
                foreach ($ordered as $project) {
                    $remaining = Task::where('user_id', $user->id)->where('project_id', $project->id)->count();
                    if ($remaining > 0) {
                        $kept[] = __(':name (kept — holds :count other task(s))', ['name' => $project->name, 'count' => $remaining]);
                        continue;
                    }
                    // project_teams.project_id has no DB cascade — detach first.
                    $project->users()->detach();
                    $project->delete();
                    $deleted['projects']++;
                }
            }

            $fresh->created_items = [];
            $fresh->save();

            $bits = [];
            foreach (['projects' => __('project(s)'), 'tasks' => __('task(s)'), 'reminders' => __('reminder(s)'), 'notes' => __('note(s)'), 'routines' => __('routine(s)')] as $kind => $label) {
                if ($deleted[$kind] > 0) {
                    $bits[] = $deleted[$kind].' '.$label;
                }
            }
            $message = $bits !== []
                ? __('↩️ Rolled back: :details deleted.', ['details' => implode(', ', $bits)])
                : __('Nothing left to delete — the plan created no surviving items.');
            if ($kept !== []) {
                $message .= ' '.__('Kept: :details.', ['details' => implode('; ', $kept)]);
            }

            return ['ok' => true, 'message' => $message, 'deleted' => $deleted, 'kept' => $kept];
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
            'weight' => isset($t['weight']) && is_numeric($t['weight']) ? max(0, min(99, (float) $t['weight'])) : 1,
            'auto_weight' => true,
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
            'impact' => $subs > 0 ? __(':count subtask(s) will also be deleted.', ['count' => $subs]) : null,
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
            'impact' => $count > 0 ? __('Hides the routine; :count recorded completion(s) are kept as history.', ['count' => $count]) : __('Hides the routine.'),
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
            'weight' => $t['weight'] ?? null,
            'subtasks' => array_map(fn ($s) => is_array($s) ? $s['title'] : $s, $t['subtasks'] ?? []),
        ];
        $roots = $this->planRoots($structure);
        $tree = [
            'projects' => array_map(fn ($r) => [
                'name' => $r['name'],
                // Reuse of an existing record — rendered as "(existing)" in
                // the structure card so the user sees it before confirming.
                'attached' => ! empty($r['project_id']),
                'attached_name' => $r['attached_name'] ?? null,
                'daily_goal_value' => $r['daily_goal_value'] ?? null,
                'daily_goal_unit' => $r['daily_goal_unit'] ?? null,
                'tasks' => array_map($taskView, $r['tasks'] ?? []),
                'subprojects' => array_map(fn ($s) => [
                    'name' => $s['name'],
                    'attached' => ! empty($s['project_id']),
                    'daily_goal_value' => $s['daily_goal_value'] ?? null,
                    'daily_goal_unit' => $s['daily_goal_unit'] ?? null,
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
        $task->reopenCompletedAncestors();

        $msg = isset($r['parent_id'])
            ? __("Subtask ':title' created under ':parent'.", ['title' => $task->title, 'parent' => $r['parent_title']])
            : __("Task ':title' created.", ['title' => $task->title]);

        return ['ok' => true, 'message' => $msg, 'id' => $task->id];
    }

    private function execTaskUpdate(array $r, $user): array
    {
        $task = Task::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $data = array_intersect_key($r, array_flip(['title', 'due_date', 'priority', 'status', 'description']));
        if (isset($data['status'])) {
            if ($data['status'] === 'completed' && $task->hasIncompleteDescendants()) {
                return ['ok' => false, 'message' => __('Complete all subtasks before completing this task.')];
            }
            $data['completed_at'] = $data['status'] === 'completed' ? ($task->completed_at ?? now()) : null;
        }
        $task->update($data);
        if (($data['status'] ?? null) && $data['status'] !== 'completed') {
            $task->reopenCompletedAncestors();
        }

        return ['ok' => true, 'message' => __("Task ':title' updated.", ['title' => $task->title]), 'id' => $task->id];
    }

    private function execTaskComplete(array $r, $user): array
    {
        $task = Task::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        if ($task->hasIncompleteDescendants()) {
            return ['ok' => false, 'message' => __('Complete all subtasks before completing this task.')];
        }
        $task->update(['status' => 'completed', 'completed_at' => $task->completed_at ?? now()]);

        return ['ok' => true, 'message' => __("Task ':title' completed.", ['title' => $task->title]), 'id' => $task->id];
    }

    private function execTaskDelete(array $r, $user): array
    {
        $task = Task::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $title = $task->title;
        $task->delete();

        return ['ok' => true, 'message' => __("Task ':title' deleted.", ['title' => $title]), 'id' => null];
    }

    private function execReminderCreate(array $r, $user): array
    {
        // reminders.description is NOT NULL in the schema — normalize nulls.
        $rem = $user->reminders()->create(array_merge($r, [
            'description' => $r['description'] ?? '',
            'recurrence_type' => Reminder::RECURRENCE_NONE, 'recurrence_interval' => 1,
        ]));

        return ['ok' => true, 'message' => __("Reminder ':title' created.", ['title' => $rem->title]), 'id' => $rem->id];
    }

    private function execReminderComplete(array $r, $user): array
    {
        $rem = Reminder::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $rem->markAsCompleted();

        return ['ok' => true, 'message' => __("Reminder ':title' completed.", ['title' => $rem->title]), 'id' => $rem->id];
    }

    private function execDelete(array $r, $user, string $model, string $label): array
    {
        $row = $model::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $title = $row->title ?? $row->name ?? "#{$row->id}";
        $row->delete();

        return ['ok' => true, 'message' => __(":label ':title' deleted.", ['label' => $label, 'title' => $title]), 'id' => null];
    }

    private function execNoteCreate(array $r, $user): array
    {
        $note = $user->notes()->create($r);

        return ['ok' => true, 'message' => __("Note ':title' created.", ['title' => $note->title]), 'id' => $note->id];
    }

    private function execNoteUpdate(array $r, $user): array
    {
        $note = Note::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $note->update(array_intersect_key($r, array_flip(['title', 'content', 'category'])));

        return ['ok' => true, 'message' => __("Note ':title' updated.", ['title' => $note->title]), 'id' => $note->id];
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
            'daily_goal_value' => $r['daily_goal_value'] ?? null,
            'daily_goal_unit' => ! empty($r['daily_goal_value']) ? ($r['daily_goal_unit'] ?? 'tasks') : null,
        ]);

        $msg = isset($r['parent_id'])
            ? __("Sub-project ':name' created under ':parent'.", ['name' => $project->name, 'parent' => $r['parent_name']])
            : __("Project ':name' created.", ['name' => $project->name]);

        return ['ok' => true, 'message' => $msg, 'id' => $project->id];
    }

    private function execProjectAddMember(array $r, $user): array
    {
        $project = Project::where('id', $r['project_id'])->where('user_id', $user->id)->firstOrFail();

        // Re-validate the selected collaborator server-side: the id was chosen
        // at proposal time and the account may be gone by execution time.
        $member = User::find((int) ($r['member_id'] ?? 0));
        if (! $member) {
            return [
                'ok' => false,
                'code' => ToolError::NOT_FOUND,
                'message' => __('The selected member no longer exists — confirm again and pick someone else.'),
                'id' => null,
            ];
        }

        $already = $project->users()->where('users.id', $member->id)->exists();
        // syncWithoutDetaching keeps existing pivot rows (dedupe-safe).
        $project->users()->syncWithoutDetaching([$member->id => ['role' => $r['role'] ?? 'member']]);

        $msg = $already
            ? __("':name' is already on ':project' (role updated to ':role').", ['name' => $member->name, 'project' => $project->name, 'role' => $r['role']])
            : __("':name' added to ':project' as ':role'.", ['name' => $member->name, 'project' => $project->name, 'role' => $r['role']]);

        return ['ok' => true, 'message' => $msg, 'id' => $project->id];
    }

    private function execNoteLink(array $r, $user): array
    {
        $note = Note::where('id', $r['note_id'])->where('user_id', $user->id)->firstOrFail();
        $link = app(NoteLinkService::class)->attach($note, $r['target_type'], (int) $r['target_id']);
        if (! $link) {
            return ['ok' => false, 'message' => __('Could not create the link (target missing or not yours).'), 'id' => null];
        }

        return ['ok' => true, 'message' => __("Note ':note' linked to :kind ':title'.", ['note' => $r['note_title'], 'kind' => $r['target_kind'], 'title' => $r['target_title']]), 'id' => $link->id];
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
        $st = $report['study'] ?? null;
        if ($st) {
            $lines[] = "Study: {$st['pages_completed']} pages · " . $this->formatSeconds($st['minutes_total'] ?? 0)
                . ' focus'
                . (($st['goal_days_total'] ?? 0) > 0 ? " · daily goal {$st['goal_days_met']}/{$st['goal_days_total']} days ({$st['goal_rate']}%)" : '');
        }
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

        return ['ok' => true, 'message' => __("Checklist ':name' added.", ['name' => $item->name]), 'id' => $item->id];
    }

    private function execChecklistToggle(array $r, $user): array
    {
        $item = ChecklistItem::where('id', $r['id'])->whereHas('task', fn ($q) => $q->where('user_id', $user->id))->firstOrFail();
        $item->update(['completed' => ! $item->completed]);

        return ['ok' => true, 'message' => ($item->completed ? __("Checklist ':name' done.", ['name' => $item->name]) : __("Checklist ':name' reopened.", ['name' => $item->name])), 'id' => $item->id];
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

        $msg = __("Routine ':title' created (:freq)", ['title' => $routine->title, 'freq' => $routine->recurrenceLabel()]);
        if ($n > 0) {
            $msg .= __(' with :count step(s)', ['count' => $n]);
        }
        if (($r['tracking_mode'] ?? 'none') !== 'none') {
            $msg .= __(' [tracking: :mode]', ['mode' => $r['tracking_mode']]);
        }

        return ['ok' => true, 'message' => $msg . '.', 'id' => $routine->id];
    }

    private function execRoutineComplete(array $r, $user): array
    {
        $routine = Routine::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        if ($routine->isAvoid()) {
            return ['ok' => false, 'message' => __("Routine ':title' is an avoid habit and cannot be checked off — staying clean is the goal.", ['title' => $routine->title]), 'id' => $routine->id];
        }
        if ($routine->completedOn($r['date'])) {
            return ['ok' => true, 'message' => __("Routine ':title' is already done for :date.", ['title' => $routine->title, 'date' => $r['date']]), 'id' => $routine->id];
        }
        RoutineCompletion::create([
            'user_id' => $user->id,
            'routine_id' => $routine->id,
            'completed_date' => $r['date'],
            'completed_at' => now(),
        ]);

        return ['ok' => true, 'message' => __("Routine ':title' marked done for :date.", ['title' => $routine->title, 'date' => $r['date']]), 'id' => $routine->id];
    }

    private function execRoutineDelete(array $r, $user): array
    {
        $routine = Routine::where('id', $r['id'])->where('user_id', $user->id)->firstOrFail();
        $title = $routine->title;
        $routine->delete();

        return ['ok' => true, 'message' => __("Routine ':title' deleted.", ['title' => $title]), 'id' => null];
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

        $what = $r['item_name'] ? __("':name' set :set", ['name' => $r['item_name'], 'set' => $r['set_no']]) : "'{$routine->title}'";
        $shown = $r['value_display'] ?? (string) $r['value'];
        $msg = __('Logged :value for :what on :date.', ['value' => $shown . (($r['unit'] ?? null) && !($r['is_time'] ?? false) ? ' ' . $r['unit'] : ''), 'what' => $what, 'date' => $r['date']]);

        // Value mode has one input per day: logging it completes the routine.
        if ($r['tracking_mode'] === Routine::TRACKING_VALUE && ! $routine->completedOn($r['date'])) {
            $routine->toggleOn($r['date']);
            $msg .= __(" Routine completed ✅");
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
                $msg .= __(" All sets done — routine completed ✅");
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

    /**
     * Public alias/canonicalization pass (shared with the tool pipeline).
     * Idempotent: safe to run on already-normalized args.
     */
    public function normalizeArgs(array $args, ?string $tool = null): array
    {
        // Security Boundary: identity/authority keys are never trusted —
        // strip them even if a future validator forgets to check.
        $args = AiSecurity::stripIdentityKeys($args);

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

        // Plan shapes: smaller models often send `project` as a plain string,
        // `projects[]` items as strings, or a single object instead of a list.
        // Coerce here (before ToolSchema::check) so the pipeline never fails
        // with a cryptic "project باید یک آرایه باشد".
        // NOTE: task_create also has {title, project:string} — coercion is
        // tool-aware so single-task calls are never mistaken for plans.
        if ($tool === null) {
            $tool = $this->inferPlanTool($args);
        }
        if ($tool === 'plan_propose') {
            $args = $this->coercePlanArgs($args);
        }

        return $args;
    }

    /**
     * Best-effort plan detection for tool-agnostic callers (pipeline envelope).
     * Returns 'plan_propose' only for UNAMBIGUOUS plan shapes — a bare
     * {title, project:string} stays untouched here because it is identical
     * to a task_create call; the tool-aware path (validateCall/pipeline
     * with an explicit tool name) still coerces it correctly.
     */
    private function inferPlanTool(array $args): ?string
    {
        if (! array_key_exists('title', $args)) {
            return null;
        }
        foreach (['projects', 'Projects', 'subprojects', 'subProjects', 'SubProjects', 'Subprojects', 'reminders', 'notes', 'routines'] as $k) {
            if (array_key_exists($k, $args)) {
                return 'plan_propose';
            }
        }
        if (array_key_exists('project', $args) && is_array($args['project'])) {
            return 'plan_propose';
        }

        return null;
    }

    /**
     * Tolerantly coerce plan_propose shapes into their canonical form.
     * Idempotent and safe to run on any tool args: it only touches keys
     * when the payload looks like a plan (has title + plan keys).
     */
    public function coercePlanArgs(array $args): array
    {
        $planKeys = ['project', 'projects', 'subprojects', 'subProjects', 'Projects', 'Subprojects', 'SubProjects', 'reminders', 'notes', 'routines'];
        $looksLikePlan = array_key_exists('title', $args) && count(array_intersect(array_keys($args), $planKeys)) > 0;
        // Also handle already-aliased single `project` string payloads.
        if (! $looksLikePlan) {
            return $args;
        }

        // Unify capital/camel variants first.
        foreach (['Projects' => 'projects', 'subProjects' => 'subprojects', 'SubProjects' => 'subprojects', 'Subprojects' => 'subprojects'] as $from => $to) {
            if (array_key_exists($from, $args) && ! array_key_exists($to, $args)) {
                $args[$to] = $args[$from];
            }
            unset($args[$from]);
        }

        // `project`: string|int => {name}. Empty string => drop (reminders/notes-only plan).
        if (array_key_exists('project', $args)) {
            $p = $args['project'];
            if (is_string($p) || is_numeric($p)) {
                $name = trim((string) $p);
                $args['project'] = $name === '' ? null : ['name' => mb_substr($name, 0, 255)];
            } elseif (is_array($p)) {
                $args['project'] = $this->coerceProjectEntry($p);
            } elseif ($p === null) {
                // keep null
            } else {
                // unexpected scalar (bool etc.) => drop so Validator gives a clear error later
                $args['project'] = null;
            }
        }

        // `projects`: string|single object => list. Items: string => {name}.
        if (array_key_exists('projects', $args)) {
            $args['projects'] = $this->coerceProjectList($args['projects']);
        }

        // `subprojects`: same treatment (belongs to the single-project tree).
        if (array_key_exists('subprojects', $args)) {
            $args['subprojects'] = $this->coerceProjectList($args['subprojects']);
        }

        // `reminders` / `notes` / `routines`: single object => wrap in list.
        foreach (['reminders', 'notes', 'routines'] as $listKey) {
            if (array_key_exists($listKey, $args) && is_array($args[$listKey]) && ! empty($args[$listKey]) && ! array_is_list($args[$listKey])) {
                // Single assoc object sent instead of a list of one.
                $args[$listKey] = [$args[$listKey]];
            }
            if (array_key_exists($listKey, $args) && is_string($args[$listKey])) {
                // A bare string can never be a valid reminder/note/routine — drop it
                // so the plan falls back to a clear "empty plan" error, not a crash.
                unset($args[$listKey]);
            }
        }

        return $args;
    }

    /**
     * Coerce a projects/subprojects value into a list of {name,...} entries.
     */
    private function coerceProjectList(mixed $value): array
    {
        if (is_string($value) || is_numeric($value)) {
            $name = trim((string) $value);
            return $name === '' ? [] : [['name' => mb_substr($name, 0, 255)]];
        }
        if (! is_array($value)) {
            return [];
        }
        // Single project object instead of a list: {name: ...} => [{...}].
        if (! empty($value) && ! array_is_list($value) && (isset($value['name']) || isset($value['title']))) {
            $value = [$value];
        }
        $out = [];
        foreach (array_values($value) as $entry) {
            if (is_string($entry) || is_numeric($entry)) {
                $name = trim((string) $entry);
                if ($name !== '') {
                    $out[] = ['name' => mb_substr($name, 0, 255)];
                }
                continue;
            }
            if (! is_array($entry)) {
                continue;
            }
            // {title: ...} instead of {name: ...} — accept it.
            if (! isset($entry['name']) && isset($entry['title']) && is_string($entry['title'])) {
                $entry['name'] = $entry['title'];
            }
            $out[] = $this->coerceProjectEntry($entry);
        }

        return $out;
    }

    /**
     * Coerce one project/subproject entry: tasks/members/subtasks tolerance.
     */
    private function coerceProjectEntry(array $entry): array
    {
        if (isset($entry['tasks'])) {
            $entry['tasks'] = $this->coerceTaskList($entry['tasks']);
        }
        if (isset($entry['subtasks'])) {
            $entry['subtasks'] = $this->coerceTaskList($entry['subtasks']);
        }
        if (isset($entry['subTasks']) && ! isset($entry['subtasks'])) {
            $entry['subtasks'] = $this->coerceTaskList($entry['subTasks']);
        }
        unset($entry['subTasks']);
        // members: "a@x.com, b@x.com" or single string => list.
        if (isset($entry['members']) && (is_string($entry['members']) || is_numeric($entry['members']))) {
            $raw = trim((string) $entry['members']);
            if ($raw === '') {
                $entry['members'] = [];
            } else {
                $parts = preg_split('/[\s,؛،\n]+/u', $raw) ?: [$raw];
                $entry['members'] = array_values(array_filter(array_map(fn ($m) => mb_substr(trim($m), 0, 255), $parts)));
            }
        }

        return $entry;
    }

    /**
     * Coerce a tasks/subtasks value: strings => {title}, single object => list.
     */
    private function coerceTaskList(mixed $value): array
    {
        if (is_string($value) || is_numeric($value)) {
            $t = trim((string) $value);
            return $t === '' ? [] : [['title' => $t]];
        }
        if (! is_array($value)) {
            return [];
        }
        if (! empty($value) && ! array_is_list($value) && (isset($value['title']) || isset($value['name']))) {
            $value = [$value];
        }
        $out = [];
        foreach (array_values($value) as $t) {
            if (is_string($t) || is_numeric($t)) {
                $title = trim((string) $t);
                if ($title !== '') {
                    $out[] = ['title' => $title];
                }
                continue;
            }
            if (! is_array($t)) {
                continue;
            }
            if (! isset($t['title']) && isset($t['name']) && (is_string($t['name']) || is_numeric($t['name']))) {
                $t['title'] = $t['name'];
            }
            if (isset($t['subtasks']) || isset($t['subTasks'])) {
                $t['subtasks'] = $this->coerceTaskList($t['subtasks'] ?? $t['subTasks'] ?? []);
            }
            unset($t['subTasks']);
            $out[] = $t;
        }

        return $out;
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

    private function fail(string $error, string $code = ToolError::VALIDATION_ERROR): array
    {
        return ['ok' => false, 'code' => $code, 'error' => $error, 'resolved' => []];
    }

    /**
     * Map raw Validator messages for plan_propose into actionable Persian-friendly
     * errors so the UI never shows a bare "project باید یک آرایه باشد".
     */
    private function friendlyPlanError(\Illuminate\Contracts\Validation\Validator $v): string
    {
        $raw = $v->errors()->first();
        $failed = $v->failed();
        $isFa = app()->getLocale() === 'fa';

        // `project` sent as string (in case coercion missed an exotic shape).
        if (isset($failed['project']['Array'])) {
            return $isFa
                ? 'ساختار پروژه اشتباه فرستاده شد (project باید آبجکت {name, tasks[]} باشد، نه رشته). لطفاً درخواست را دوباره بفرستید — اصلاح خودکار فعال شد.'
                : 'The project structure was malformed (project must be an object {name, tasks[]}, not a string). Please send the request again — auto-fix is now on.';
        }
        if (isset($failed['projects']['Array'])) {
            return $isFa
                ? 'ساختار projects اشتباه است (باید لیستی از آبجکت‌ها باشد). لطفاً دوباره تلاش کنید.'
                : 'The projects structure was malformed (must be a list of objects). Please try again.';
        }
        if (isset($failed['subprojects']['Array'])) {
            return $isFa
                ? 'ساختار subprojects اشتباه است (باید لیست باشد). لطفاً دوباره تلاش کنید.'
                : 'The subprojects structure was malformed (must be a list). Please try again.';
        }

        // Title too long etc. — keep the field context.
        return $raw;
    }
}
