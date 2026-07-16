<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerForeignKeyReturningPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerForeignKeyReturningPlan;

$posts = [
    ['post_id' => 0, 'post_title' => 'Unassigned'],
    ['post_id' => 10, 'post_title' => 'Imported alpha'],
    ['post_id' => 20, 'post_title' => 'Imported beta'],
];
$postmeta = [
    ['meta_id' => 100, 'post_id' => 10, 'meta_key' => '_import_batch'],
    ['meta_id' => 101, 'post_id' => 10, 'meta_key' => '_thumbnail_id'],
    ['meta_id' => 102, 'post_id' => 20, 'meta_key' => '_import_batch'],
];

$result = SQLiteTriggerForeignKeyReturningPlan::deleteParents(
    $posts,
    $postmeta,
    static fn (array $row): bool => $row['post_id'] === 10,
    [
        'parent_key' => 'post_id',
        'child_key' => 'post_id',
        'on_delete' => 'SET DEFAULT',
        'child_default' => 0,
        'deferred' => true,
    ],
    [
        [
            'name' => 'after_post_delete_audit',
            'timing' => 'after',
            'event' => 'delete',
            'action' => 'insert-child',
            'row' => ['meta_id' => 991, 'post_id' => 'old.post_id', 'meta_key' => '_delete_audit'],
            'values' => ['post_id' => 'old.post_id', 'meta_key' => '_delete_audit'],
        ],
    ],
    ['old.post_id', ['expr' => 'old.post_title', 'as' => 'deleted_title']],
    'post_id'
);

echo json_encode([
    'remaining_posts' => array_column($result['parent'], 'post_id'),
    'postmeta_post_ids' => array_column($result['child'], 'post_id'),
    'returning' => $result['yielded'][0]['returning'],
    'foreign_key_actions' => array_column($result['foreign_key_actions'], 'action'),
    'deferred_violations' => $result['foreign_key_violations'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
