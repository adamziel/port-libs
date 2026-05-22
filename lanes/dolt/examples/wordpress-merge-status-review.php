<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\MergeStatusTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-merge-review.php';
$mergeStatus = new MergeStatusTable();

return [
    'mergeStatus' => $mergeStatus->statusRow(
        $fixture['isMerging'],
        $fixture['source'],
        $fixture['sourceCommit'],
        $fixture['target'],
        $fixture['dataConflictTables'],
        $fixture['constraintViolationTables'],
        $fixture['schemaConflictTables'],
    ),
    'conflictRows' => $mergeStatus->conflictRows(
        $fixture['conflictTables'],
        $fixture['schemaConflictRows'],
        $fixture['rootObjectConflicts'],
    ),
];
