<?php

return [
    'features' => [
        'files' => (bool) env('BETA_ENABLE_FILES', false),
        'ai' => (bool) env('BETA_ENABLE_AI', false),
    ],
];
