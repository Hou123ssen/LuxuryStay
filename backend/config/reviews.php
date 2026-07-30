<?php

return [
    'risk' => [
        'high_risk_threshold' => (int) env('REVIEW_HIGH_RISK_THRESHOLD', 80),
        'account_age_threshold_hours' => (int) env('REVIEW_ACCOUNT_AGE_THRESHOLD_HOURS', 24),
        'burst_window_minutes' => (int) env('REVIEW_BURST_WINDOW_MINUTES', 60),
        'max_reviews_per_property_in_burst_window' => (int) env('REVIEW_BURST_MAX_PER_PROPERTY', 3),
        'shared_network_window_minutes' => (int) env('REVIEW_SHARED_NETWORK_WINDOW_MINUTES', 60),
        'max_shared_network_users' => (int) env('REVIEW_SHARED_NETWORK_MAX_USERS', 2),
        'duplicate_comment_similarity_threshold' => (int) env('REVIEW_DUPLICATE_COMMENT_SIMILARITY_THRESHOLD', 90),
        'hash_secret' => env('REVIEW_RISK_HASH_KEY', env('APP_KEY')),
        'weights' => [
            'ACCOUNT_TOO_NEW' => 20,
            'REVIEW_BURST' => 35,
            'DUPLICATE_CONTENT' => 55,
            'SHARED_NETWORK_CLUSTER' => 25,
            'RATE_LIMIT_PATTERN' => 20,
            'INVALID_BOOKING_SIGNAL' => 50,
        ],
    ],
];
