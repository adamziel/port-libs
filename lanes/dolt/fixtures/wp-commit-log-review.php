<?php

declare(strict_types=1);

return [
    'headHash' => 'wp-merge-media',
    'commits' => [
        [
            'commit_hash' => 'wp-init',
            'committer' => 'WordPress Importer',
            'email' => 'importer@example.test',
            'date' => '2026-05-22 09:00:00',
            'message' => 'Initialize data repository',
            'parents' => [],
            'refs' => [],
            'changedTables' => [],
        ],
        [
            'commit_hash' => 'wp-import-base',
            'committer' => 'WordPress Importer',
            'email' => 'importer@example.test',
            'date' => '2026-05-22 09:10:00',
            'message' => 'Import WXR posts and pages',
            'parents' => ['wp-init'],
            'refs' => ['refs/tags/import-base'],
            'changedTables' => ['wp_posts', 'wp_postmeta', 'wp_options'],
        ],
        [
            'commit_hash' => 'wp-review-main',
            'committer' => 'Migration Reviewer',
            'email' => 'reviewer@example.test',
            'date' => '2026-05-22 09:20:00',
            'message' => 'Review public post statuses',
            'parents' => ['wp-import-base'],
            'refs' => [],
            'author' => 'Editorial Reviewer',
            'author_email' => 'editor@example.test',
            'author_date' => '2026-05-22 09:18:00',
            'changedTables' => ['wp_posts'],
        ],
        [
            'commit_hash' => 'wp-media-branch',
            'committer' => 'Media Importer',
            'email' => 'media@example.test',
            'date' => '2026-05-22 09:25:00',
            'message' => 'Prepare media backfill branch',
            'parents' => ['wp-import-base'],
            'refs' => ['refs/heads/media-import'],
            'changedTables' => ['wp_postmeta'],
        ],
        [
            'commit_hash' => 'wp-merge-media',
            'committer' => 'Migration Reviewer',
            'email' => 'reviewer@example.test',
            'date' => '2026-05-22 09:35:00',
            'message' => 'Merge media backfill into reviewed import',
            'parents' => ['wp-review-main', 'wp-media-branch'],
            'refs' => ['refs/heads/main', 'refs/tags/import-reviewed'],
            'changedTables' => ['wp_posts', 'wp_postmeta'],
        ],
    ],
    'expectedLogMessages' => [
        'Merge media backfill into reviewed import',
        'Prepare media backfill branch',
        'Review public post statuses',
        'Import WXR posts and pages',
        'Initialize data repository',
    ],
    'expectedHeadRefs' => 'HEAD -> main, tag: import-reviewed',
    'expectedMergeParents' => 'wp-review-main, wp-media-branch',
    'expectedMainlineMessages' => [
        'Review public post statuses',
        'Import WXR posts and pages',
        'Initialize data repository',
    ],
    'expectedReviewRangeMessages' => [
        'Merge media backfill into reviewed import',
        'Prepare media backfill branch',
        'Review public post statuses',
    ],
    'expectedMediaPromotionMessages' => [
        'Merge media backfill into reviewed import',
        'Prepare media backfill branch',
    ],
    'expectedPostTableLogMessages' => [
        'Merge media backfill into reviewed import',
        'Review public post statuses',
        'Import WXR posts and pages',
    ],
    'expectedPostMetaTableLogMessages' => [
        'Merge media backfill into reviewed import',
        'Prepare media backfill branch',
        'Import WXR posts and pages',
    ],
    'expectedMergeOnlyMessages' => [
        'Merge media backfill into reviewed import',
    ],
    'expectedCheckpointMessages' => [
        'Merge media backfill into reviewed import',
        'Prepare media backfill branch',
        'Review public post statuses',
        'Import WXR posts and pages',
    ],
];
