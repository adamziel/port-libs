<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext298301Plan;

$candidate = static function (int $next, array $rowids): array {
    $rows = [];
    foreach (array_values($rowids) as $index => $rowid) {
        $rows[] = [
            'row_number' => $index + 1,
            'current_rowid' => $rowid,
            'option_name' => "copied_option_{$next}_{$rowid}",
            'status' => "retry{$next}",
        ];
    }

    return [
        'status' => "rowvalue-update-delete-returning-window-current-source-next{$next}-ready",
        'after_ready' => true,
        'retry_window_rows' => $rows,
    ];
};

$summary = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext298301Plan::prepare([
    $candidate(294, [2, 5]),
    $candidate(295, [3, 6, 8]),
    $candidate(296, [4]),
    $candidate(297, [7, 9]),
]);

$payload = [
    'status' => $summary['status'],
    'candidateStatuses' => $summary['ready_candidate_statuses'],
    'next298Receipt' => $summary['next298_receipt'],
    'next299Ledger' => $summary['next299_ledger'],
    'next300Handoff' => $summary['next300_handoff'],
    'next301Seal' => $summary['next301_seal'],
    'next301Ready' => $summary['next301_ready'],
    'wordpressUse' => 'Copied wp_options imports can prepare next298-301 after-ready receipts from next294-297 row-value UPDATE/DELETE RETURNING window current-source candidates before publishing the next source.',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['status'] !== 'rowvalue-update-delete-returning-window-current-source-next298-301-after-ready'
        || strlen($payload['next301Seal']) !== 64
        || $payload['next301Ready'] !== true
    ) {
        fwrite(STDERR, "wordpress-rowvalue-returning-window-current-source-next298-301-after-ready self-test failed\n");
        exit(1);
    }

    echo "wordpress-rowvalue-returning-window-current-source-next298-301-after-ready self-test passed\n";
    return;
}

return $payload;
