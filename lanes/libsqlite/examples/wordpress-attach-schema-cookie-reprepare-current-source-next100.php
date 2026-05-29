<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachSchemaCookieRepreparePlan;

$schemas = [
    'main' => [
        'schema_cookie' => 100,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 101, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 199, 'commit' => false],
        ],
        'tables' => ['sqlite_schema', 'wp_options', 'wp_posts'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 7,
        'tables' => ['sqlite_schema', 'wp_option_stage'],
        'file' => '',
        'temp' => true,
    ],
    'site' => [
        'schema_cookie' => 20,
        'wal_schema_cookie' => 21,
        'tables' => ['sqlite_schema', 'wp_2_options', 'wp_terms'],
        'file' => '/srv/wp/site.sqlite',
    ],
    'archive' => [
        'schema_cookie' => 30,
        'tables' => ['sqlite_schema', 'wp_archive_options'],
        'file' => '/srv/wp/archive.sqlite',
    ],
];

$statements = [
    [
        'name' => 'cte-site-reader',
        'sql' => 'WITH recent_options AS (SELECT option_name FROM [site].[wp_2_options]) SELECT option_name FROM recent_options ORDER BY option_name',
    ],
    [
        'name' => 'recursive-main-reader',
        'sql' => 'WITH RECURSIVE option_walk(name) AS (SELECT option_name FROM [main].[wp_options] UNION ALL SELECT option_name FROM [main].[wp_options]) SELECT name FROM option_walk',
        'active' => true,
    ],
    [
        'name' => 'not-materialized-temp-insert',
        'sql' => 'WITH stage_names AS NOT MATERIALIZED (SELECT option_name FROM [temp].[wp_option_stage]) INSERT INTO [main].[wp_options](option_name) SELECT option_name FROM stage_names',
    ],
    [
        'name' => 'main-schema-alias-reader',
        'sql' => 'SELECT name FROM [main].[sqlite_master] WHERE type = ?',
    ],
    [
        'name' => 'future-blog-reader',
        'sql' => 'WITH future AS (SELECT option_value FROM [blog100].[wp_options]) SELECT option_value FROM future',
    ],
];

$events = [
    ['op' => 'schema_write', 'schema' => 'site', 'object' => 'wp_2_options'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 102],
    ['op' => 'attach', 'schema' => 'blog100', 'schema_cookie' => 1, 'tables' => ['wp_options'], 'file' => '/srv/wp/blog100.sqlite'],
    ['op' => 'detach', 'schema' => 'archive'],
];

echo json_encode(
    SQLiteAttachSchemaCookieRepreparePlan::schemaCookieRepreparePlan($schemas, $statements, $events),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;
