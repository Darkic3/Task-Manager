<?php

return [
    'projects' => ['label' => 'Projects', 'description' => 'Project boards and project details.', 'default' => true],
    'tasks' => ['label' => 'Tasks', 'description' => 'Task lists, details, checklists, and status changes.', 'default' => true],
    'planner' => ['label' => 'Daily planner', 'description' => 'My Day planning and daily logs.', 'default' => true],
    'notes' => ['label' => 'Notes', 'description' => 'Notes, notebooks, labels, and exports.', 'default' => true],
    'routines' => ['label' => 'Routines', 'description' => 'Routines and habit tracking.', 'default' => true],
    'workouts' => ['label' => 'Workouts', 'description' => 'Workout plans, sessions, and reports.', 'default' => true],
    'track' => ['label' => 'Track', 'description' => 'Habit tracking views.', 'default' => true],
    'time_tracking' => ['label' => 'Time tracking', 'description' => 'Timers, time entries, and time reports.', 'default' => true],
    'reports' => ['label' => 'Reports', 'description' => 'Productivity and progress reports.', 'default' => true],
    'reminders' => ['label' => 'Reminders', 'description' => 'Personal reminders and calendar.', 'default' => true],
    'files' => ['label' => 'Files', 'description' => 'File manager and file uploads.', 'default' => (bool) env('BETA_ENABLE_FILES', false)],
    'ai' => ['label' => 'AI assistant', 'description' => 'Lina, AI providers, and AI-powered actions.', 'default' => (bool) env('BETA_ENABLE_AI', false)],
];
