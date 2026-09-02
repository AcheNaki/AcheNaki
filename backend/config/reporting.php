<?php

return [
    'anonymous_header' => 'X-Anonymous-Reporter',
    'anonymous_token_prefix' => 'ar1_',
    'duplicate_window_seconds' => 180,
    'report_rate_limit_per_minute' => 12,
    'session_rate_limit_per_minute' => 20,

    'aggregation' => [
        'window_seconds' => 30 * 60,
        'recency_weights' => [
            ['max_age_seconds' => 5 * 60, 'weight' => 100],
            ['max_age_seconds' => 15 * 60, 'weight' => 75],
            ['max_age_seconds' => 30 * 60, 'weight' => 40],
        ],
        'mixed_max_weight_difference_percent' => 15,
        'score_components' => [
            'agreement_percent' => 50,
            'recency_percent' => 20,
            'volume_percent' => 30,
        ],
        'volume_scores' => [
            1 => 25,
            2 => 45,
            3 => 65,
            4 => 75,
            5 => 85,
            6 => 100,
        ],
        'confidence' => [
            'medium' => ['minimum_score' => 60, 'minimum_reporters' => 3, 'minimum_supporters' => 3],
            'high' => ['minimum_score' => 80, 'minimum_reporters' => 6, 'minimum_supporters' => 5],
        ],
        'status_since' => [
            'minimum_supporters_with_estimates' => 2,
            'maximum_estimate_spread_seconds' => 15 * 60,
        ],
        'listing_max_limit' => 100,
        'listing_default_limit' => 25,
    ],

    'events' => [
        'minimum_confidence' => 'MEDIUM',
        'stabilization_seconds' => 2 * 60,
        'inference_version' => 1,
    ],

    'analytics' => [
        'timezone' => 'Asia/Dhaka',
    ],
];
