<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\DiffTabularRenderer;
use PortLibs\Dolt\TableDiff;
use PortLibs\Dolt\TableSchema;

$fixture = require dirname(__DIR__) . '/fixtures/wp-diff-mode-review.php';
$schema = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_content', 'tag' => 2, 'type' => 'longtext'],
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
$renderer = new DiffTabularRenderer();

return [
    'row' => $renderer->render('wp_posts', $schema, $rows, ['diffMode' => 'row']),
    'line' => $renderer->render('wp_posts', $schema, $rows, ['diffMode' => 'line']),
    'inPlace' => $renderer->render('wp_posts', $schema, $rows, ['diffMode' => 'in-place']),
    'context' => $renderer->render('wp_posts', $schema, $rows),
];
