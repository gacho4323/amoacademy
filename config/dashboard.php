<?php

return [
    'cache_keys' => [
        'stats' => 'dashboard_stats',
        'activities' => 'dashboard_recent_activities',
    ],
    'cache_ttl' => [
        'stats' => 30, // minutes
        'activities' => 15, // minutes
    ],
    'limits' => [
        'recent_courses' => 3,
        'recent_users' => 5,
        'recent_purchases' => 3,
        'total_activities' => 10,
    ],
];