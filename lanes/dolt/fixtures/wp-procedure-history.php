<?php

declare(strict_types=1);

$procedure = static function (
    string $name,
    string $createStmt,
    string $createdAt,
    ?string $modifiedAt = null,
    ?string $sqlMode = '',
): array {
    return [
        'name' => $name,
        'create_stmt' => $createStmt,
        'created_at' => $createdAt,
        'modified_at' => $modifiedAt ?? $createdAt,
        'sql_mode' => $sqlMode,
    ];
};

$preparePostsV1 = $procedure(
    'wp_import_prepare_posts',
    "CREATE PROCEDURE wp_import_prepare_posts() SELECT ID, post_title FROM wp_posts WHERE post_status <> 'trash'",
    '2026-05-22 14:00:00'
);
$preparePostsV2 = $procedure(
    'wp_import_prepare_posts',
    "CREATE PROCEDURE wp_import_prepare_posts() SELECT ID, post_title, post_type FROM wp_posts WHERE post_status <> 'trash'",
    '2026-05-22 14:00:00',
    '2026-05-22 14:10:00'
);
$preparePostsWorking = $procedure(
    'wp_import_prepare_posts',
    "CREATE PROCEDURE wp_import_prepare_posts() SELECT ID, post_title, post_type, post_modified_gmt FROM wp_posts WHERE post_status IN ('publish', 'future', 'draft')",
    '2026-05-22 14:00:00',
    '2026-05-22 14:20:00'
);
$mediaQueue = $procedure(
    'wp_import_media_queue',
    "CREATE PROCEDURE wp_import_media_queue() SELECT ID, guid FROM wp_posts WHERE post_type = 'attachment'",
    '2026-05-22 14:05:00'
);
$reviewCursor = $procedure(
    'wp_import_review_cursor',
    "CREATE PROCEDURE wp_import_review_cursor(batch_id INT) SELECT ID, post_status FROM wp_posts WHERE import_batch_id = batch_id",
    '2026-05-22 14:20:00',
    '2026-05-22 14:20:00',
    'NO_ENGINE_SUBSTITUTION'
);

return [
    'commits' => [
        [
            'commit_hash' => 'wp-procedures-1',
            'committer' => 'Migration Bot <migrate@example.test>',
            'commit_date' => '2026-05-22 14:00:00',
            'procedures' => [$preparePostsV1],
        ],
        [
            'commit_hash' => 'wp-procedures-2',
            'committer' => 'Migration Bot <migrate@example.test>',
            'commit_date' => '2026-05-22 14:05:00',
            'procedures' => [$preparePostsV1, $mediaQueue],
        ],
        [
            'commit_hash' => 'wp-procedures-3',
            'committer' => 'Migration Bot <migrate@example.test>',
            'commit_date' => '2026-05-22 14:10:00',
            'procedures' => [$preparePostsV2, $mediaQueue],
        ],
    ],
    'workingProcedures' => [
        $preparePostsWorking,
        $reviewCursor,
    ],
    'expectedHistoryTotal' => 5,
    'expectedWorkingDiffTypes' => ['modified', 'added', 'removed'],
    'expectedWorkingProcedureNames' => [
        'wp_import_prepare_posts',
        'wp_import_review_cursor',
        'wp_import_media_queue',
    ],
];
