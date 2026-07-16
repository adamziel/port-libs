<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$candidate = static function (array $rowids): array {
    $rows = [];
    foreach (array_values($rowids) as $index => $rowid) {
        $rows[] = [
            'row_number' => $index + 1,
            'current_rowid' => $rowid,
            'option_name' => "copied_option_{$rowid}",
            'status' => 'retry-ready',
        ];
    }

    return [
        'status' => 'rowvalue-update-delete-returning-window-ready-publication-candidate',
        'after_ready' => true,
        'retry_window_rows' => $rows,
    ];
};

$summary = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::prepareReadyWindowPublicationMetadata([
    $candidate([2, 5]),
    $candidate([3, 6, 8]),
    $candidate([4]),
    $candidate([7, 9]),
]);

$payload = [
    'status' => $summary['status'],
    'candidateStatuses' => $summary['ready_candidate_statuses'],
    'publicationReceipt' => $summary['publication_receipt'],
    'publicationLedger' => $summary['publication_ledger'],
    'publicationHandoff' => $summary['publication_handoff'],
    'publicationSeal' => $summary['publication_seal'],
    'publicationReady' => $summary['publication_ready'],
    'applicationUse' => 'Copied wp_options imports can prepare ready publication receipts from row-value UPDATE/DELETE RETURNING window candidates before publishing the next source.',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['status'] !== 'rowvalue-update-delete-returning-window-ready-publication-metadata'
        || strlen($payload['publicationSeal']) !== 64
        || $payload['publicationReady'] !== true
    ) {
        fwrite(STDERR, "application-rowvalue-returning-window-ready-publication-metadata self-test failed\n");
        exit(1);
    }

    echo "application-rowvalue-returning-window-ready-publication-metadata self-test passed\n";
    return;
}

return $payload;
