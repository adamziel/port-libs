<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\MergeStatusTable;
use PortLibs\Dolt\ConstraintViolationsTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-merge-review.php';
$mergeStatus = new MergeStatusTable();
$constraintViolations = new ConstraintViolationsTable();

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
    'statusGuidance' => $mergeStatus->statusGuidance(
        $fixture['isMerging'],
        $fixture['conflictTables'],
        $fixture['schemaConflictRows'],
        $fixture['constraintViolationTables'],
    ),
    'commitGuidance' => $mergeStatus->commitUnmergedPaths(
        $fixture['conflictTables'],
        $fixture['schemaConflictRows'],
        $fixture['constraintViolationTables'],
    ),
    'mergeConstraintError' => $constraintViolations->unresolvedMergeError($fixture['constraintViolationsByTable']),
    'mergeConstraintSummary' => $constraintViolations->mergeViolationSummaryText($fixture['constraintViolationsByTable']),
];
