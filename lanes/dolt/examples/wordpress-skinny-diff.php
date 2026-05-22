<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\TableDiff;

$fixture = require dirname(__DIR__) . '/fixtures/wp-skinny-diff.php';
$warnings = [];

$rows = (new TableDiff())->diffTableRowsForSchemas(
    $fixture['fromRows'],
    $fixture['toRows'],
    'ID',
    $fixture['fromSchema'],
    $fixture['toSchema'],
    null,
    null,
    $fixture['fromCommit'],
    null,
    $fixture['toCommit'],
    null,
    $warnings,
    true,
    $fixture['includeColumns'],
);

return [
    'rows' => $rows,
    'warnings' => $warnings,
];
