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
    'mergeArtifactPrelude' => $mergeStatus->mergeArtifactPrelude(
        $fixture['conflictTables'],
        $fixture['schemaConflictRows'],
        $fixture['constraintViolationTables'],
        $fixture['rootObjectConflicts'],
    ),
    'mergeFailureSummary' => $mergeStatus->mergeFailureSummary(
        $fixture['conflictTables'],
        $fixture['schemaConflictRows'],
        $fixture['constraintViolationTables'],
        $fixture['rootObjectConflicts'],
    ),
    'mergeFailureTranscript' => $mergeStatus->mergeArtifactPrelude(
        $fixture['conflictTables'],
        $fixture['schemaConflictRows'],
        $fixture['constraintViolationTables'],
        $fixture['rootObjectConflicts'],
    )
        . "\n"
        . $mergeStatus->mergeFailureSummary(
            $fixture['conflictTables'],
            $fixture['schemaConflictRows'],
            $fixture['constraintViolationTables'],
            $fixture['rootObjectConflicts'],
        ),
    'successfulMergeStats' => $mergeStatus->mergeSuccessStats($fixture['successfulMergeStats']),
    'noCommitMergeTranscript' => $mergeStatus->mergeSuccessTranscript(
        $fixture['successfulMergeStats'],
        $fixture['noCommitMergeOptions'],
    ),
    'squashMergeTranscript' => $mergeStatus->mergeSuccessTranscript(
        $fixture['successfulMergeStats'],
        $fixture['squashMergeOptions'],
    ),
    'fastForwardMergeTranscript' => $mergeStatus->mergeCliTranscript(
        $fixture['successfulMergeStats'],
        $fixture['fastForwardMergeOptions'],
    ),
    'noFfMergeTranscript' => $mergeStatus->mergeCliTranscript(
        $fixture['successfulMergeStats'],
        $fixture['noFfMergeOptions'],
    ),
    'ffOnlyFailure' => $mergeStatus->mergeCliTranscript([], $fixture['ffOnlyFailureOptions']),
    'ffOnlyNoFfError' => $mergeStatus->mergeCliTranscript([], $fixture['ffOnlyNoFfOptions']),
    'ffOnlySquashError' => $mergeStatus->mergeCliTranscript([], $fixture['ffOnlySquashOptions']),
    'upToDateMergeTranscript' => $mergeStatus->mergeSuccessTranscript([], ['upToDate' => true]),
    'abortState' => $mergeStatus->abortMergeState($fixture['abortPreservedWorkingTables']),
    'mergeProcedureRows' => [
        'fastForward' => $mergeStatus->mergeProcedureRow($fixture['fastForwardProcedureOptions']),
        'noFf' => $mergeStatus->mergeProcedureRow($fixture['noFfProcedureOptions']),
        'noCommit' => $mergeStatus->mergeProcedureRow($fixture['noCommitProcedureOptions']),
        'conflicts' => $mergeStatus->mergeProcedureRow($fixture['conflictProcedureOptions']),
        'upToDate' => $mergeStatus->mergeProcedureRow($fixture['upToDateProcedureOptions']),
        'ahead' => $mergeStatus->mergeProcedureRow($fixture['aheadProcedureOptions']),
        'abort' => $mergeStatus->mergeProcedureRow($fixture['abortProcedureOptions']),
    ],
    'mergeConstraintError' => $constraintViolations->unresolvedMergeError($fixture['constraintViolationsByTable']),
    'mergeConstraintSummary' => $constraintViolations->mergeViolationSummaryText($fixture['constraintViolationsByTable']),
];
