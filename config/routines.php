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
    */

    'periods' => [
        'morning' => ['label' => 'Morning', 'icon' => 'bi-sunrise', 'order' => 1],
        'noon' => ['label' => 'Noon', 'icon' => 'bi-brightness-high', 'order' => 2],
        'afternoon' => ['label' => 'Afternoon', 'icon' => 'bi-sun', 'order' => 3],
        'evening' => ['label' => 'Evening', 'icon' => 'bi-sunset', 'order' => 4],
        'night' => ['label' => 'Night', 'icon' => 'bi-moon-stars', 'order' => 5],
    ],

];
