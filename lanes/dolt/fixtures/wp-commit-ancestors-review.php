<?php

declare(strict_types=1);

$commitLog = require __DIR__ . '/wp-commit-log-review.php';

return [
    'headHash' => $commitLog['headHash'],
    'commits' => $commitLog['commits'],
    'expectedAncestorRows' => [
        ['commit_hash' => 'wp-merge-media', 'parent_hash' => 'wp-review-main', 'parent_index' => 0],
        ['commit_hash' => 'wp-merge-media', 'parent_hash' => 'wp-media-branch', 'parent_index' => 1],
    ],
    'expectedParentMessages' => [
        [
            'parent_index' => 0,
            'parent_hash' => 'wp-review-main',
            'message' => 'Review public post statuses',
        ],
        [
            'parent_index' => 1,
            'parent_hash' => 'wp-media-branch',
            'message' => 'Prepare media backfill branch',
        ],
    ],
];
