<?php

declare(strict_types=1);

return [
    'isMerging' => true,
    'source' => 'migration/import-branch',
    'sourceCommit' => 'b2274926e0dcd84aab000ee242df5b5e75689eef',
    'target' => 'refs/heads/main',
    'dataConflictTables' => ['wp_posts'],
    'constraintViolationTables' => ['wp_postmeta'],
    'schemaConflictTables' => ['wp_options'],
    'conflictTables' => [
        ['name' => 'wp_posts', 'numConflicts' => 2],
    ],
    'schemaConflictRows' => [
        ['name' => 'wp_options'],
    ],
    'rootObjectConflicts' => [
        ['name' => 'wp_import_preview_view', 'numConflicts' => 1],
    ],
    'expectedMergeStatusRow' => [
        'is_merging' => true,
        'source' => 'migration/import-branch',
        'source_commit' => 'b2274926e0dcd84aab000ee242df5b5e75689eef',
        'target' => 'refs/heads/main',
        'unmerged_tables' => 'wp_posts, wp_postmeta, wp_options',
    ],
    'expectedConflictRows' => [
        ['table' => 'wp_posts', 'num_conflicts' => 2],
        ['table' => 'wp_options', 'num_conflicts' => 0],
        ['table' => 'wp_import_preview_view', 'num_conflicts' => 1],
    ],
];
