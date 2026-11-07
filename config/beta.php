<?php

return [
    'features' => [
        'files' => (bool) env('BETA_ENABLE_FILES', false),
        'ai' => (bool) env('BETA_ENABLE_AI', false),
        'laws' => (bool) env('BETA_ENABLE_LAWS', false),
        'vocabulary' => (bool) env('BETA_ENABLE_VOCABULARY', false),
        'reminders' => (bool) env('BETA_ENABLE_REMINDERS', false),
    ],
];
