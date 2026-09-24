<?php

namespace App\Services;

use App\Models\ChecklistItem;
use App\Models\Note;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Routine;
use App\Models\RoutineCompletion;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AiToolService
{
    public const TOOLS = [
        'task_create', 'task_update', 'task_complete', 'task_delete',
        'reminder_create', 'reminder_complete', 'reminder_delete',
        'note_create', 'note_update', 'note_delete',
        'project_create',
        'checklist_add', 'checklist_toggle',
        'routine_create', 'routine_complete', 'routine_delete', 'routine_log',
        'plan_propose',
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
            $this->fn('task_update', 'Edit a task by ID', [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'due_date' => $date(),
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'status' => ['type' => 'string', 'enum' => ['to_do', 'in_progress', 'on_hold', 'in_review', 'completed']],
                'description' => ['type' => 'string'],
            ], ['id']),
            $this->fn('task_complete', 'Mark a task completed by ID', [
                'id' => ['type' => 'integer'],
            ], ['id']),
            $this->fn('task_delete', 'Delete a task by ID (shows subtask impact before confirm)', [
                'id' => ['type' => 'integer'],
            ], ['id']),
            $this->fn('reminder_create', 'Create a reminder', [
                'title' => ['type' => 'string'],
                'date' => $date(),
                'time' => ['type' => 'string', 'description' => 'HH:MM 24h'],
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                'description' => ['type' => 'string'],
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
            $this->fn('checklist_add', 'Add a checklist item to a task', [
                'task_id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ], ['task_id', 'name']),
            $this->fn('checklist_toggle', 'Toggle a checklist item completed state', [
                'id' => ['type' => 'integer'],
            ], ['id']),
            $this->fn('routine_create', 'Create a recurring routine. Ask the user first when tracking is wanted but the unit kind is unknown', [
                'title' => ['type' => 'string', 'description' => 'Routine title'],
                'frequency' => ['type' => 'string', 'enum' => ['daily', 'weekly', 'monthly', 'every_n_days']],
                'days' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::WEEK_DAYS], 'description' => 'Weekdays for weekly frequency'],
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
                'value' => ['type' => 'number', 'description' => 'Logged number'],
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
            [
                'type' => 'function',
                'function' => [
                    'name' => 'plan_propose',
                    'description' => 'Propose a whole multi-level build (project with sub-projects, tasks and subtasks) as ONE plan. Use this for programs and multi-part builds instead of many single calls',
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
                                'description' => 'Max 3 sub-projects (project-tree plans only)',
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
            'task_complete' => $this->validateOwned($args, $user, Task::class, 'id'),
            'task_delete' => $this->validateOwned($args, $user, Task::class, 'id'),
            'reminder_create' => $this->validateReminderCreate($args),
            'reminder_complete' => $this->validateOwned($args, $user, Reminder::class, 'id'),
            'reminder_delete' => $this->validateOwned($args, $user, Reminder::class, 'id'),
            'note_create' => $this->validateNoteCreate($args),
            'note_update' => $this->validateNoteUpdate($args, $user),
            'note_delete' => $this->validateOwned($args, $user, Note::class, 'id'),
            'project_create' => $this->validateProjectCreate($args, $user),
            'checklist_add' => $this->validateChecklistAdd($args, $user),
            'checklist_toggle' => $this->validateChecklistToggle($args, $user),
            'routine_create' => $this->validateRoutineCreate($args),
            'routine_complete' => $this->validateRoutineComplete($args, $user),
            'routine_delete' => $this->validateOwned($args, $user, Routine::class, 'id'),
            'routine_log' => $this->validateRoutineLog($args, $user),
            'plan_propose' => $this->validatePlanPropose($args),
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
            'project_create' => ['title' => isset($resolved['parent_id']) ? 'Create sub-project' : 'Create project', 'rows' => $this->rows($resolved, ['name', 'parent_name', 'status', 'description'])],
            'task_create' => ['title' => isset($resolved['parent_id']) ? 'Create subtask' : 'Create task', 'rows' => $this->rows($resolved, ['title', 'project_name', 'parent_title', 'due_date', 'priority', 'status'])],
            'reminder_create' => ['title' => 'Create reminder', 'rows' => $this->rows($resolved, ['title', 'date', 'time', 'priority'])],
            'note_create' => ['title' => 'Create note', 'rows' => $this->rows($resolved, ['title', 'category'])],
            'routine_create' => ['title' => 'Create routine', 'rows' => $this->rows($resolved, ['title', 'frequency', 'days_label', 'tracking_mode', 'value_label', 'time_period', 'description'])],
            'routine_delete' => $this->previewRoutineDelete($resolved),
            'routine_log' => $this->previewRoutineLog($resolved),
            'plan_propose' => $this->previewPlan($resolved),
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
                'checklist_add' => $this->execChecklistAdd($resolved, $user),
                'checklist_toggle' => $this->execChecklistToggle($resolved, $user),
                'routine_create' => $this->execRoutineCreate($resolved, $user),
                'routine_complete' => $this->execRoutineComplete($resolved, $user),
                'routine_delete' => $this->execRoutineDelete($resolved, $user),
                'routine_log' => $this->execRoutineLog($resolved, $user),
                // Plans never execute as a single action; they run phase by phase.
                'plan_propose' => ['ok' => false, 'message' => 'Plans run phase by phase after structure approval.', 'id' => null],
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
            $project = Project::where('user_id', $user->id)->orderBy('id')->first();
            if (! $project) {
                return $this->fail('You have no project yet — create a project first.');
            }
            $projectId = $project->id;
            $projectName = $project->name;
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
            'id' => 'required|integer',
            'title' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:to_do,in_progress,on_hold,in_review,completed',
            'description' => 'nullable|string',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $task = Task::where('id', $args['id'])->where('user_id', $user->id)->first();
        if (! $task) {
            return $this->fail('Task not found or not yours.');
        }

        return ['ok' => true, 'error' => null, 'resolved' => array_merge(['id' => $task->id], array_filter([
            'title' => $args['title'] ?? null,
            'due_date' => $args['due_date'] ?? null,
            'priority' => $args['priority'] ?? null,
            'status' => $args['status'] ?? null,
            'description' => $args['description'] ?? null,
        ], fn ($x) => $x !== null))];
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
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }

        return ['ok' => true, 'error' => null, 'resolved' => [
            'title' => $args['title'],
            'date' => $args['date'] ?? null,
            'time' => $args['time'] ?? null,
            'priority' => $args['priority'] ?? 'medium',
            'description' => $args['description'] ?? null,
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
        $v = Validator::make($args, ['task_id' => 'required|integer', 'name' => 'required|string|max:255']);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $task = Task::where('id', $args['task_id'])->where('user_id', $user->id)->first();
        if (! $task) {
            return $this->fail('Task not found or not yours.');
        }

        return ['ok' => true, 'error' => null, 'resolved' => ['task_id' => $task->id, 'name' => $args['name']]];
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
            return $this->fail('Weekly routines need at least one weekday.');
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
            'days_label' => $daysLabel,
            'tracking_mode' => $tracking,
            'value_kind' => $tracking === 'value' ? $args['value_kind'] : null,
            'value_unit' => $tracking === 'value' ? ($args['value_unit'] ?? null) : null,
            'value_label' => $tracking === 'value' ? ($args['value_label'] ?? null) : null,
            'steps' => $steps,
        ]];
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
            'value' => 'required|numeric|min:0|max:1000000',
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
            'date' => $date,
            'value' => $args['value'],
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
     * Caps: 1 project, 3 sub-projects, 30 tasks, 100 subtasks.
     */
    private function validatePlanPropose(array $args): array
    {
        $args['subprojects'] = $args['subProjects'] ?? $args['subprojects'] ?? [];
        unset($args['subProjects']);
        if (! is_array($args['subprojects'])) {
            return $this->fail('subprojects must be a list.');
        }
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
            'routines' => 'nullable|array|max:' . self::MAX_PLAN_ROUTINES,
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }

        // A plan is EITHER a project tree OR a routine set — never both.
        $hasProjectTree = ! empty($args['project']['name']) || ! empty($args['subprojects']);
        $hasRoutines = ! empty($args['routines']);
        if ($hasProjectTree && $hasRoutines) {
            return $this->fail('A plan holds either a project tree or routines, not both. Split into two plans.');
        }
        if (! $hasProjectTree && ! $hasRoutines) {
            return $this->fail('The plan is empty: add a project with tasks or at least one routine.');
        }

        if ($hasRoutines) {
            return $this->validatePlanRoutines($args['title'], $args['routines']);
        }
        if (empty($args['project']['name'])) {
            return $this->fail('The project needs a name.');
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

        $taskCount = count($direct) + array_sum(array_map(fn ($s) => count($s['tasks']), $subs));
        $subCount = array_sum(array_map(
            fn ($s) => array_sum(array_map(fn ($t) => count($t['subtasks']), $s['tasks'])),
            $subs
        )) + array_sum(array_map(fn ($t) => count($t['subtasks']), $direct));

        if ($taskCount < 1) {
            return $this->fail('The plan must contain at least one task.');
        }
        if ($taskCount > \App\Models\AiPlan::MAX_TASKS) {
            return $this->fail('Too many tasks (max ' . \App\Models\AiPlan::MAX_TASKS . '). Split into smaller plans.');
        }
        if ($subCount > \App\Models\AiPlan::MAX_SUBTASKS) {
            return $this->fail('Too many subtasks (max ' . \App\Models\AiPlan::MAX_SUBTASKS . '). Split into smaller plans.');
        }

        $structure = [
            'project' => [
                'name' => trim((string) $args['project']['name']),
                'description' => $args['project']['description'] ?? null,
                'tasks' => $direct,
            ],
            'subprojects' => $subs,
            'routines' => [],
        ];

        return ['ok' => true, 'error' => null, 'resolved' => [
            'title' => trim((string) $args['title']),
            'structure' => $structure,
            'totals' => [
                'subprojects' => count($subs),
                'tasks' => $taskCount,
                'subtasks' => $subCount,
                'routines' => 0,
                'steps' => 0,
            ],
        ]];
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

        $structure = ['project' => null, 'subprojects' => [], 'routines' => $clean];

        return ['ok' => true, 'error' => null, 'resolved' => [
            'title' => trim($title),
            'structure' => $structure,
            'totals' => [
                'subprojects' => 0,
                'tasks' => 0,
                'subtasks' => 0,
                'routines' => count($clean),
                'steps' => array_sum(array_map(fn ($r) => count($r['steps']), $clean)),
            ],
        ]];
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

        $subCount = count($structure['subprojects']);
        $taskCount = count($structure['project']['tasks'])
            + array_sum(array_map(fn ($s) => count($s['tasks']), $structure['subprojects']));
        $subTaskCount = array_sum(array_map(fn ($t) => count($t['subtasks']), $structure['project']['tasks']))
            + array_sum(array_map(
                fn ($s) => array_sum(array_map(fn ($t) => count($t['subtasks']), $s['tasks'])),
                $structure['subprojects']
            ));

        $phase = fn ($key, $label, $total) => [
            'key' => $key, 'label' => $label, 'total' => $total, 'done' => 0,
            'status' => $total > 0 ? 'locked' : 'done', 'result' => null,
        ];

        return [
            $phase('project', 'Create project', 1),
            $phase('subprojects', 'Create sub-projects', $subCount),
            $phase('tasks', 'Create daily tasks', $taskCount),
            $phase('subtasks', 'Add exercise subtasks', $subTaskCount),
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

            if ($key === 'routines') {
                $ids = [];
                foreach ($structure['routines'] as $r) {
                    $result = $this->execRoutineCreate($r, $user);
                    $ids[] = $result['id'];
                }
                $phase['result'] = ['routine_ids' => $ids];
                $message = count($ids) . ' routine(s) created.';
            } elseif ($key === 'project') {
                $project = $user->projects()->create([
                    'name' => $structure['project']['name'],
                    'description' => $structure['project']['description'] ?? null,
                    'status' => 'in_progress',
                    'type' => 'project',
                    'sort_order' => 0,
                ]);
                $phase['result'] = ['project_id' => $project->id, 'project_name' => $project->name];
                $message = "Project '{$project->name}' created.";
            } elseif ($key === 'subprojects') {
                $projectId = $phases[0]['result']['project_id'] ?? null;
                $project = $projectId ? Project::where('id', $projectId)->where('user_id', $user->id)->first() : null;
                if (! $project) {
                    return ['ok' => false, 'message' => 'Plan project is missing. Cancel and start over.', 'phase' => $phase];
                }
                $ids = [];
                foreach ($structure['subprojects'] as $sub) {
                    $created = $user->projects()->create([
                        'name' => $sub['name'],
                        'description' => $sub['description'] ?? null,
                        'status' => 'in_progress',
                        'parent_id' => $project->id,
                        'type' => 'project',
                        'sort_order' => 0,
                    ]);
                    $ids[] = $created->id;
                }
                $phase['result'] = ['sub_ids' => $ids];
                $message = count($ids) . ' sub-project(s) created.';
            } elseif ($key === 'tasks') {
                $map = $this->planProjectMap($phases);
                if (! $map) {
                    return ['ok' => false, 'message' => 'Plan project is missing. Cancel and start over.', 'phase' => $phase];
                }
                [$projectId, $subIds] = $map;
                $taskIds = ['direct' => [], 'subs' => []];
                foreach ($structure['project']['tasks'] as $t) {
                    $taskIds['direct'][] = $this->createPlanTask($user, $projectId, null, $t);
                }
                foreach ($structure['subprojects'] as $si => $sub) {
                    foreach ($sub['tasks'] as $t) {
                        $taskIds['subs'][$si][] = $this->createPlanTask($user, $subIds[$si], null, $t);
                    }
                }
                $phase['result'] = ['task_ids' => $taskIds];
                $message = $phase['total'] . ' task(s) created.';
            } else { // subtasks
                $map = $this->planProjectMap($phases);
                $taskIds = $phases[2]['result']['task_ids'] ?? null;
                if (! $map || ! $taskIds) {
                    return ['ok' => false, 'message' => 'Plan tasks are missing. Cancel and start over.', 'phase' => $phase];
                }
                [$projectId, $subIds] = $map;
                $n = 0;
                foreach ($structure['project']['tasks'] as $ti => $t) {
                    foreach ($t['subtasks'] as $s) {
                        $this->createPlanTask($user, $projectId, $taskIds['direct'][$ti], ['title' => $s['title']]);
                        $n++;
                    }
                }
                foreach ($structure['subprojects'] as $si => $sub) {
                    foreach ($sub['tasks'] as $ti => $t) {
                        foreach ($t['subtasks'] as $s) {
                            $this->createPlanTask($user, $subIds[$si] ?? $projectId, $taskIds['subs'][$si][$ti], ['title' => $s['title']]);
                            $n++;
                        }
                    }
                }
                $phase['result'] = ['created' => $n];
                $message = $n . ' subtask(s) added.';
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

    private function planProjectMap(array $phases): ?array
    {
        $projectId = $phases[0]['result']['project_id'] ?? null;
        if (! $projectId) {
            return null;
        }

        return [$projectId, $phases[1]['result']['sub_ids'] ?? []];
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
        $tree = [
            'project' => $structure['project'] ? [
                'name' => $structure['project']['name'],
                'tasks' => array_map(fn ($t) => [
                    'title' => $t['title'],
                    'due_date' => $t['due_date'],
                    'subtasks' => array_map(fn ($s) => $s['title'], $t['subtasks']),
                ], $structure['project']['tasks']),
            ] : null,
            'subprojects' => array_map(fn ($s) => [
                'name' => $s['name'],
                'tasks' => array_map(fn ($t) => [
                    'title' => $t['title'],
                    'due_date' => $t['due_date'],
                    'subtasks' => array_map(fn ($x) => $x['title'], $t['subtasks']),
                ], $s['tasks']),
            ], $structure['subprojects'] ?? []),
            'routines' => array_map(fn ($r) => [
                'title' => $r['title'],
                'frequency' => $r['frequency'],
                'tracking_mode' => $r['tracking_mode'] ?? 'none',
                'steps' => array_map(fn ($s) => $s['name'], $r['steps'] ?? []),
            ], $structure['routines'] ?? []),
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
        $rem = $user->reminders()->create(array_merge($r, [
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
            ['k' => 'Value', 'v' => (string) $resolved['value'] . ($resolved['unit'] ? ' ' . $resolved['unit'] : '')],
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
        $msg = "Logged {$r['value']}" . ($r['unit'] ? " {$r['unit']}" : '') . " for {$what} on {$r['date']}.";

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
