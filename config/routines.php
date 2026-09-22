<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routine time-of-day periods
    |--------------------------------------------------------------------------
    |
    | Lightweight, config-driven "time buckets" a routine can be assigned to
    | instead of an exact start/end time. Keys are persisted in
    | `routines.time_period`, so keeping a stable key lets us migrate to
    | user-defined periods later without touching existing rows.
    |
    | `at` is an approximate hour used as a scheduling reference for the
    | completion tracker (offset vs. scheduled).
    |
    */

    'periods' => [
        'morning' => ['label' => 'Morning', 'icon' => 'bi-sunrise', 'color' => '#f59e0b', 'at' => 8, 'order' => 1],
        'noon' => ['label' => 'Noon', 'icon' => 'bi-brightness-high', 'color' => '#f97316', 'at' => 12, 'order' => 2],
        'afternoon' => ['label' => 'Afternoon', 'icon' => 'bi-sun', 'color' => '#0ea5e9', 'at' => 15, 'order' => 3],
        'evening' => ['label' => 'Evening', 'icon' => 'bi-sunset', 'color' => '#ec4899', 'at' => 18, 'order' => 4],
        'night' => ['label' => 'Night', 'icon' => 'bi-moon-stars', 'color' => '#4f46e5', 'at' => 21, 'order' => 5],
    ],

];
