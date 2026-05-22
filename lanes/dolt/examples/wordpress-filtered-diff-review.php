<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\TableDiff;

$fixture = require dirname(__DIR__) . '/fixtures/wp-filtered-review-diff.php';
$differ = new TableDiff();
$rows = $differ->diffTableRows(
    $fixture['fromRows'],
    $fixture['toRows'],
    'ID',
    $fixture['columns'],
    $fixture['fromCommit'],
    null,
    $fixture['toCommit'],
    null,
);

return [
    'where' => $fixture['where'],
    'limit' => $fixture['limit'],
    'rows' => $differ->filterDiffTableRows($rows, $fixture['where'], $fixture['limit']),
];
