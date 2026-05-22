<?php

declare(strict_types=1);

$schemaObject = static function (string $type, string $name, string $fragment): array {
    return [
        'type' => $type,
        'name' => $name,
        'fragment' => $fragment,
        'extra' => null,
        'sql_mode' => '',
    ];
};

$previewViewV1 = $schemaObject(
    'view',
    'wp_import_preview',
    "CREATE VIEW wp_import_preview AS SELECT ID, post_title FROM wp_posts WHERE post_status = 'publish'"
);
$previewViewV2 = $schemaObject(
    'view',
    'wp_import_preview',
    "CREATE VIEW wp_import_preview AS SELECT ID, post_title, post_modified_gmt FROM wp_posts WHERE post_status IN ('publish', 'future')"
);
$guardTrigger = $schemaObject(
    'trigger',
    'wp_import_guard',
    "CREATE TRIGGER wp_import_guard BEFORE INSERT ON wp_posts FOR EACH ROW SET NEW.post_title = TRIM(NEW.post_title)"
);

return [
    'commits' => [
        [
            'commit_hash' => 'wp-schema-1',
            'committer' => 'Migration Bot <migrate@example.test>',
            'commit_date' => '2026-05-22 13:00:00',
            'schemas' => [$previewViewV1],
        ],
        [
            'commit_hash' => 'wp-schema-2',
            'committer' => 'Migration Bot <migrate@example.test>',
            'commit_date' => '2026-05-22 13:05:00',
            'schemas' => [$previewViewV1, $guardTrigger],
        ],
        [
            'commit_hash' => 'wp-schema-3',
            'committer' => 'Migration Bot <migrate@example.test>',
            'commit_date' => '2026-05-22 13:10:00',
            'schemas' => [$previewViewV2, $guardTrigger],
        ],
    ],
    'workingSchemas' => [
        $schemaObject(
            'event',
            'wp_import_checkpoint_event',
            'CREATE EVENT wp_import_checkpoint_event ON SCHEDULE EVERY 10 MINUTE DO SELECT 1'
        ),
        $schemaObject(
            'view',
            'wp_import_review_view',
            "CREATE VIEW wp_import_review_view AS SELECT ID, post_status FROM wp_posts WHERE post_status <> 'trash'"
        ),
        $schemaObject(
            'view',
            'wp_import_preview',
            "CREATE VIEW wp_import_preview AS SELECT ID, post_title, post_status, post_modified_gmt FROM wp_posts WHERE post_status IN ('publish', 'future')"
        ),
    ],
    'expectedHistoryCounts' => [
        'total' => 5,
        'view' => 3,
        'trigger' => 2,
    ],
    'expectedWorkingDiffTypes' => ['added', 'modified', 'added', 'removed'],
    'expectedWorkingObjectNames' => [
        'wp_import_checkpoint_event',
        'wp_import_preview',
        'wp_import_review_view',
        'wp_import_guard',
    ],
];
