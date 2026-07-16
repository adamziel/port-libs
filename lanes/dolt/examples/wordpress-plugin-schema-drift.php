<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\TableDiff;

$fixture = require dirname(__DIR__) . '/fixtures/wp-plugin-schema-drift.php';
$warnings = [];

$rows = (new TableDiff())->diffTableRowsForSchemas(
    $fixture['fromRows'],
    $fixture['toRows'],
    'event_id',
    $fixture['fromSchema'],
    $fixture['toSchema'],
    $fixture['targetSchema'],
    $fixture['targetSchema'],
    $fixture['fromCommit'],
    null,
    $fixture['toCommit'],
    null,
    $warnings,
);

return [
    'rows' => $rows,
    'warnings' => $warnings,
];
