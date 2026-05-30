<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaForeignKeyCheck;

$tables = [
    'wp_posts' => [
        ['rowid' => 1, 'ID' => 1, 'post_title' => 'Hello world'],
        ['rowid' => 2, 'ID' => 2, 'post_title' => 'Sample page'],
    ],
    'wp_postmeta' => [
        ['rowid' => 101, 'meta_id' => 101, 'post_id' => 1, 'meta_key' => '_edit_lock'],
        ['rowid' => 102, 'meta_id' => 102, 'post_id' => 404, 'meta_key' => '_thumbnail_id'],
        ['rowid' => 103, 'meta_id' => 103, 'post_id' => null, 'meta_key' => '_draft_parent'],
    ],
    'wp_comments' => [
        ['rowid' => 201, 'comment_ID' => 201, 'comment_post_ID' => 2],
        ['rowid' => 202, 'comment_ID' => 202, 'comment_post_ID' => 999],
    ],
    'wp_options_expected' => [
        ['option_name' => 'siteurl'],
        ['option_name' => 'home'],
    ],
    'wp_options_shadow' => [
        ['rowid' => 301, 'expected_option' => 'SITEURL'],
        ['rowid' => 302, 'expected_option' => 'missing_plugin_option'],
    ],
];

$result = SQLitePragmaForeignKeyCheck::execute('PRAGMA foreign_key_check', $tables, [
    ['id' => 0, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => ['post_id' => 'ID']],
    ['id' => 1, 'table' => 'wp_comments', 'parent' => 'wp_posts', 'columns' => ['comment_post_ID' => 'ID']],
    [
        'id' => 2,
        'table' => 'wp_options_shadow',
        'parent' => 'wp_options_expected',
        'columns' => [
            ['child' => 'expected_option', 'parent' => 'option_name', 'affinity' => 'text', 'collation' => 'nocase'],
        ],
    ],
]);

echo json_encode([
    'pragma' => $result['pragma'],
    'violations' => count($result['rows']),
    'rows' => $result['rows'],
], JSON_PRETTY_PRINT) . "\n";
