<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachSchemaCookieRepreparePlan;

$schemas = [
    'main' => [
        'schema_cookie' => 40,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 41, 'commit' => true],
        ],
        'tables' => ['sqlite_schema', 'wp_options', 'wp_posts'],
        'file' => '/srv/wp/current.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 5,
        'tables' => ['sqlite_schema', 'wp_option_stage'],
        'file' => '',
        'temp' => true,
    ],
    'site' => [
        'schema_cookie' => 12,
        'tables' => ['sqlite_schema', 'wp_2_options', 'wp_2_posts'],
        'file' => '/srv/wp/site.sqlite',
        'cache' => 'shared',
    ],
    'archive' => [
        'schema_cookie' => 18,
        'tables' => ['sqlite_schema', 'wp_archive_options'],
        'file' => '/srv/wp/archive.sqlite',
        'cache' => 'shared',
    ],
];

$statements = [
    ['name' => 'main-active-reader', 'sql' => 'SELECT option_value FROM [main].[wp_options] WHERE option_name = ?', 'active' => true],
    ['name' => 'site-reader', 'sql' => 'SELECT option_value FROM [site].[wp_2_options] WHERE option_name = ?'],
    ['name' => 'site-update', 'sql' => 'UPDATE [site].[wp_2_posts] SET post_title = ? WHERE ID = ?'],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_name FROM wp_options WHERE option_name LIKE ?'],
    ['name' => 'future-blog-reader', 'sql' => 'SELECT option_value FROM [blog103].[wp_options]'],
    ['name' => 'main-insert-from-temp', 'sql' => 'INSERT INTO [main].[wp_options](option_name) SELECT option_name FROM [temp].[wp_option_stage]'],
];

$events = [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 42],
    ['op' => 'schema_write', 'schema' => 'site', 'object' => 'wp_2_options'],
    ['op' => 'schema_write', 'schema' => 'temp', 'object' => 'wp_options'],
    ['op' => 'attach', 'schema' => 'blog103', 'schema_cookie' => 3, 'tables' => ['wp_options'], 'file' => '/srv/wp/blog103.sqlite'],
];

$cache = [
    'main' => ['file' => '/srv/wp/current.sqlite', 'cache' => 'shared', 'schema_cookie' => 41, 'generation' => 8],
    'site' => ['file' => '/srv/wp/site.sqlite', 'cache' => 'shared', 'schema_cookie' => 12, 'generation' => 9],
    'blog103' => ['file' => '/srv/wp/blog103.sqlite', 'cache' => 'shared', 'schema_cookie' => 2, 'generation' => 11],
];

echo json_encode(
    SQLiteAttachSchemaCookieRepreparePlan::sharedCacheRepreparePlan($schemas, $statements, $events, $cache),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;
