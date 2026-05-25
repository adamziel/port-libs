<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\MergeStatusTable;
use PortLibs\Dolt\ConstraintViolationsTable;
use PortLibs\Dolt\PreviewMergeConflictsTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-merge-review.php';
$mergeStatus = new MergeStatusTable();
$constraintViolations = new ConstraintViolationsTable();
$previewConflicts = new PreviewMergeConflictsTable();

try {
    $previewSchemaConflictRows = $previewConflicts->conflictRows(
        $fixture['previewMergeBaseRows'],
        $fixture['previewMergeOurRows'],
        $fixture['previewMergeTheirRows'],
        $fixture['previewMergePrimaryKey'],
        $fixture['previewMergeColumns'],
        $fixture['previewMergeRightRootish'],
        $fixture['previewSchemaConflictCount'],
    );
    $previewSchemaConflictError = null;
} catch (InvalidArgumentException $exception) {
    $previewSchemaConflictRows = [];
    $previewSchemaConflictError = $exception->getMessage();
}

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
    'rootObjectConflictRows' => $mergeStatus->rootObjectConflictRows(
        $fixture['rootObjectConflictDetails'],
    ),
    'previewConflictSummaryRows' => $previewConflicts->summaryRows(
        $fixture['previewDataConflictTables'],
        $fixture['previewSchemaConflictTables'],
    ),
    'previewConflictRows' => $previewConflicts->conflictRows(
        $fixture['previewMergeBaseRows'],
        $fixture['previewMergeOurRows'],
        $fixture['previewMergeTheirRows'],
        $fixture['previewMergePrimaryKey'],
        $fixture['previewMergeColumns'],
        $fixture['previewMergeRightRootish'],
    ),
    'previewKeylessConflictRows' => $previewConflicts->keylessConflictRows(
        $fixture['previewKeylessBaseRows'],
        $fixture['previewKeylessOurRows'],
        $fixture['previewKeylessTheirRows'],
        $fixture['previewKeylessColumns'],
        $fixture['previewMergeRightRootish'],
    ),
    'previewSchemaConflictRows' => $previewSchemaConflictRows,
    'previewSchemaConflictError' => $previewSchemaConflictError,
    'previewSchemaConflictDescriptionRows' => $previewConflicts->schemaConflictRows(
        $fixture['previewSchemaConflictDescriptions'],
    ),
    'resolvedSchemaConflictState' => $mergeStatus->resolveSchemaConflicts(
        $fixture['schemaConflictRows'],
        $fixture['schemaConflictResolutionTables'],
    ),
    'resolvedRootObjectConflictState' => $mergeStatus->resolveRootObjectConflicts(
        $fixture['rootObjectConflicts'],
        $fixture['rootObjectResolutionObjects'],
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
