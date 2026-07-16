<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\DiffSqlRenderer;
use PortLibs\Dolt\TableDiff;
use PortLibs\Dolt\TableSchema;

$fixture = require dirname(__DIR__) . '/fixtures/wp-posts-diff.php';
$schema = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'text'],
    ['name' => 'post_status', 'tag' => 3, 'type' => 'varchar(20)'],
    ['name' => 'post_modified_gmt', 'tag' => 4, 'type' => 'datetime'],
]);
$rows = (new TableDiff())->diffTableRows(
    $fixture['fromRows'],
    $fixture['toRows'],
    'ID',
    $fixture['columns'],
    $fixture['fromCommit'],
    null,
    $fixture['toCommit'],
    null,
);
$renderer = new DiffSqlRenderer();

return [
    'all' => $renderer->render('wp_posts', $schema, $rows),
    'added' => $renderer->render('wp_posts', $schema, $rows, ['filter' => 'added']),
    'modified' => $renderer->render('wp_posts', $schema, $rows, ['filter' => 'modified']),
    'removed' => $renderer->render('wp_posts', $schema, $rows, ['filter' => 'removed']),
];
