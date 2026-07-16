<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\TableDeltaMatcher;
use PortLibs\Dolt\TableDiff;

$fixture = require dirname(__DIR__) . '/fixtures/wp-primary-key-warning.php';

$summaryWarnings = [];
$summaryRows = (new TableDeltaMatcher())->summaryRows(
    $fixture['fromTables'],
    $fixture['toTables'],
    null,
    false,
    [],
    $summaryWarnings,
    $fixture['fromCommit'],
    $fixture['toCommit'],
);

$statWarnings = [];
$statRow = (new TableDiff())->diffStatRow(
    $fixture['tableName'],
    $fixture['fromRows'],
    $fixture['toRows'],
    $fixture['primaryKey'],
    $fixture['fromSchema'],
    $fixture['toSchema'],
    $statWarnings,
    false,
    false,
    $fixture['fromCommit'],
    $fixture['toCommit'],
);

return [
    'summaryRows' => $summaryRows,
    'summaryWarnings' => $summaryWarnings,
    'statRow' => $statRow,
    'statWarnings' => $statWarnings,
];
