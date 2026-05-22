<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\DiffStatRenderer;
use PortLibs\Dolt\TableDeltaMatcher;
use PortLibs\Dolt\TableDiff;
use PortLibs\Dolt\TableSchema;

$posts = require dirname(__DIR__) . '/fixtures/wp-diff-stat-review.php';
$differ = new TableDiff();
$warnings = [];
$postStat = $differ->diffStatRow(
    $posts['tableName'],
    $posts['fromRows'],
    $posts['toRows'],
    $posts['primaryKey'],
    $posts['fromSchema'],
    $posts['toSchema'],
    $warnings,
);

$auditSchema = TableSchema::fromColumns([
    ['name' => 'event_id', 'tag' => 20, 'type' => 'int', 'primaryKey' => true],
    ['name' => 'message', 'tag' => 21, 'type' => 'text'],
]);
$auditWarnings = [];
$auditStat = $differ->diffStatRow(
    'wp_import_audit',
    [['event_id' => 1, 'message' => 'created import plan']],
    [
        ['event_id' => 1, 'message' => 'created import plan'],
        ['event_id' => 2, 'message' => 'reviewed public content'],
    ],
    'event_id',
    $auditSchema,
    $auditSchema,
    $auditWarnings,
);

$logSchema = TableSchema::fromColumns([
    ['name' => 'event_type', 'tag' => 30, 'type' => 'varchar(40)'],
    ['name' => 'message', 'tag' => 31, 'type' => 'text'],
    ['name' => 'created_gmt', 'tag' => 32, 'type' => 'datetime'],
]);
$logWarnings = [];
$logStat = $differ->diffStatRow(
    'wp_import_log',
    [
        ['event_type' => 'scan', 'message' => 'started media scan', 'created_gmt' => '2026-05-22 09:00:00'],
    ],
    [
        ['event_type' => 'scan', 'message' => 'finished media scan', 'created_gmt' => '2026-05-22 09:05:00'],
        ['event_type' => 'post', 'message' => 'imported post 501', 'created_gmt' => '2026-05-22 09:06:00'],
        ['event_type' => 'post', 'message' => 'queued post 504 review', 'created_gmt' => '2026-05-22 09:07:00'],
    ],
    null,
    $logSchema,
    $logSchema,
    $logWarnings,
    true,
    true,
);

$optionsBefore = TableSchema::fromColumns([
    ['name' => 'option_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'option_name', 'tag' => 2, 'type' => 'varchar(191)'],
]);
$optionsAfter = TableSchema::fromColumns([
    ['name' => 'option_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'option_name', 'tag' => 2, 'type' => 'varchar(191)'],
    ['name' => 'autoload', 'tag' => 3, 'type' => 'varchar(20)'],
]);

$tables = [
    [
        'from_table_name' => 'wp_import_log',
        'to_table_name' => 'wp_import_log',
        'diff_type' => TableDeltaMatcher::DIFF_MODIFIED,
        'from_schema' => $logSchema,
        'to_schema' => $logSchema,
        'keyless' => true,
        'statRows' => [$logStat],
    ],
    [
        'from_table_name' => 'wp_import_audit',
        'to_table_name' => 'wp_import_audit',
        'diff_type' => TableDeltaMatcher::DIFF_MODIFIED,
        'from_schema' => $auditSchema,
        'to_schema' => $auditSchema,
        'statRows' => [$auditStat],
    ],
    [
        'from_table_name' => 'wp_posts',
        'to_table_name' => 'wp_posts',
        'diff_type' => TableDeltaMatcher::DIFF_MODIFIED,
        'from_schema' => $posts['fromSchema'],
        'to_schema' => $posts['toSchema'],
        'statRows' => [$postStat],
    ],
    [
        'from_table_name' => 'wp_options',
        'to_table_name' => 'wp_options',
        'diff_type' => TableDeltaMatcher::DIFF_MODIFIED,
        'from_schema' => $optionsBefore,
        'to_schema' => $optionsAfter,
        'statRows' => [],
    ],
];

$renderer = new DiffStatRenderer();

return [
    'all' => $renderer->render($tables),
    'postsOnly' => $renderer->render($tables, ['tableNames' => ['wp_posts']]),
    'postsJson' => $renderer->renderJson($tables, ['tableNames' => ['wp_posts']]),
    'keylessLog' => $renderer->render($tables, ['tableNames' => ['wp_import_log']]),
    'keylessLogJson' => $renderer->renderJson($tables, ['tableNames' => ['wp_import_log']]),
    'schemaOnlyOptions' => $renderer->render($tables, ['tableNames' => ['wp_options']]),
    'schemaOnlyOptionsJson' => $renderer->renderJson($tables, ['tableNames' => ['wp_options']]),
    'unchangedUsers' => $renderer->render($tables, ['tableNames' => ['wp_users']]),
    'unchangedUsersJson' => $renderer->renderJson($tables, ['tableNames' => ['wp_users']]),
    'warnings' => array_merge($warnings, $auditWarnings, $logWarnings),
];
